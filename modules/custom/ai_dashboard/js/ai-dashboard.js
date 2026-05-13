(function (Drupal, once) {
  'use strict';

  const SERVICES = ['kassa', 'crm', 'controlroom', 'frontend', 'mailing', 'facturatie', 'planning', 'iot'];
  const POLL_MS = 30000;
  const DOWN_WINDOW_MS = 5 * 60 * 1000;

  const KIBANA_BASE_URL = 'https://control-room.integration-project-2026-groep-2.my.be';
  const KIBANA_INDEX_PATTERN = 'controlroom-logs*';
  const KIBANA_SERVICE_FIELD = 'service.keyword';
  const KIBANA_WINDOW_PRE_SEC = 5 * 60;
  const KIBANA_WINDOW_POST_SEC = 60;

  const downSince = new Map();

  function sortKey(inc) {
    return typeof inc.original_ts === 'number' ? inc.original_ts : (inc.received_at || 0);
  }

  let csrfToken = null;
  let lastSeenTs = 0;
  let pollStatusEl = null;
  let pillsEl = null;
  let rowsEl = null;
  let detailDialog = null;
  let detailTitleEl = null;
  let detailMetaEl = null;
  let detailBodyEl = null;

  Drupal.behaviors.aiDashboard = {
    attach: function (context) {
      once('ai-dashboard-init', '#ai-dashboard-app', context).forEach(function (root) {
        pollStatusEl = root.querySelector('#ai-dashboard-poll-status');
        pillsEl = root.querySelector('#ai-dashboard-pills');
        rowsEl = root.querySelector('#ai-dashboard-rows');
        detailDialog = root.querySelector('#ai-dashboard-detail');
        detailTitleEl = root.querySelector('#ai-dashboard-detail-title');
        detailMetaEl = root.querySelector('#ai-dashboard-detail-meta');
        detailBodyEl = root.querySelector('#ai-dashboard-detail-body');
        const closeBtn = root.querySelector('#ai-dashboard-detail-close');
        if (closeBtn && detailDialog) {
          closeBtn.addEventListener('click', () => detailDialog.close());
        }
        if (detailDialog) {
          detailDialog.addEventListener('click', (event) => {
            if (event.target === detailDialog) {
              detailDialog.close();
            }
          });
        }
        renderPills();
        bootstrap();
      });
    },
  };

  async function bootstrap() {
    try {
      const r = await fetch('/session/token', { credentials: 'same-origin' });
      csrfToken = (await r.text()).trim();
    } catch (e) {
      setStatus('CSRF token unavailable');
      return;
    }
    await poll();
    setInterval(poll, POLL_MS);
  }

  async function poll() {
    try {
      const url = '/api/ai/incidents?since=' + encodeURIComponent(lastSeenTs);
      const res = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
      });
      if (!res.ok) {
        setStatus('poll error ' + res.status);
        return;
      }
      const data = await res.json();
      const incidents = Array.isArray(data.incidents) ? data.incidents : [];
      const ordered = incidents.slice().sort((a, b) => {
        const ka = sortKey(a);
        const kb = sortKey(b);
        if (ka !== kb) return ka - kb;
        return (a.id || 0) - (b.id || 0);
      });
      for (const inc of ordered) {
        prependRow(inc);
        if (typeof inc.received_at === 'number' && inc.received_at > lastSeenTs) {
          lastSeenTs = inc.received_at;
        }
        if (inc.event_type === 'incident_diagnosed' && inc.service) {
          downSince.set(inc.service, sortKey(inc) * 1000);
        }
        if (inc.event_type === 'incident_resolved' && inc.service) {
          downSince.delete(inc.service);
        }
      }
      renderPills();
      setStatus('updated ' + formatClock(new Date()));
    } catch (e) {
      setStatus('poll failed');
    }
  }

  function setStatus(text) {
    if (pollStatusEl) {
      pollStatusEl.textContent = text;
    }
  }

  function renderPills() {
    if (!pillsEl) {
      return;
    }
    pillsEl.replaceChildren();
    const now = Date.now();
    for (const svc of SERVICES) {
      const downAt = downSince.get(svc);
      const isDown = typeof downAt === 'number' && (now - downAt) < DOWN_WINDOW_MS;
      const pill = document.createElement('span');
      pill.className = 'ai-dashboard-pill' + (isDown ? ' is-down' : '');
      const dot = document.createElement('span');
      dot.className = 'ai-dashboard-pill-dot';
      pill.appendChild(dot);
      const label = document.createElement('span');
      label.textContent = svc;
      pill.appendChild(label);
      pillsEl.appendChild(pill);
    }
  }

  function prependRow(inc) {
    if (!rowsEl) {
      return;
    }
    const empty = rowsEl.querySelector('.ai-dashboard-empty');
    if (empty) {
      empty.remove();
    }
    const existing = rowsEl.querySelector('tr[data-id="' + cssEscape(String(inc.id)) + '"]');
    if (existing) {
      existing.remove();
    }
    const tr = document.createElement('tr');
    tr.dataset.id = String(inc.id);
    tr.addEventListener('click', () => showIncidentDetail(inc.id));

    tr.appendChild(td('ai-dashboard-time', formatClock(new Date((inc.received_at || 0) * 1000))));
    tr.appendChild(td('ai-dashboard-service', inc.service || '—'));

    const tSeverity = td('');
    tSeverity.appendChild(makeBadge('severity', inc.severity));
    tr.appendChild(tSeverity);

    const tConfidence = td('');
    tConfidence.appendChild(makeBadge('confidence', inc.confidence || '—'));
    tr.appendChild(tConfidence);

    const tType = td('');
    tType.appendChild(makeTypeBadge(inc.event_type));
    tr.appendChild(tType);

    tr.appendChild(td('ai-dashboard-summary', inc.root_cause_preview || ''));
    tr.appendChild(td('ai-dashboard-arrow', '→'));

    rowsEl.insertBefore(tr, rowsEl.firstChild);
  }

  function td(cls, text) {
    const el = document.createElement('td');
    if (cls) {
      el.className = cls;
    }
    if (text !== undefined) {
      el.textContent = text;
    }
    return el;
  }

  function makeBadge(category, value) {
    const span = document.createElement('span');
    const slug = String(value || '').toLowerCase().replace(/[^a-z0-9_]/g, '_') || 'unknown';
    span.className = 'ai-dashboard-badge ai-dashboard-badge--' + category + '-' + slug;
    span.textContent = String(value || '—');
    return span;
  }

  function makeTypeBadge(eventType) {
    const map = {
      incident_diagnosed: ['diagnosed', 'Diagnose'],
      incident_skipped: ['skipped', 'Overgeslagen'],
      incident_circuit_open: ['circuit_open', 'Circuit open'],
      incident_resolved: ['resolved', 'Opgelost'],
    };
    const entry = map[eventType] || ['diagnosed', eventType || '—'];
    const span = document.createElement('span');
    span.className = 'ai-dashboard-badge ai-dashboard-badge--type-' + entry[0];
    span.textContent = entry[1];
    return span;
  }

  function formatClock(d) {
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const ss = String(d.getSeconds()).padStart(2, '0');
    return hh + ':' + mm + ':' + ss;
  }

  function cssEscape(s) {
    return s.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  async function showIncidentDetail(id) {
    if (!detailDialog) {
      return;
    }
    detailBodyEl.replaceChildren(makeLoading());
    detailMetaEl.replaceChildren();
    detailTitleEl.textContent = 'Incident #' + id;
    detailDialog.showModal();

    let detail;
    try {
      const res = await fetch('/api/ai/incidents/' + encodeURIComponent(id), {
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
      });
      if (!res.ok) {
        renderDetailError('Kon incident niet laden (' + res.status + ').');
        return;
      }
      detail = await res.json();
    } catch (e) {
      renderDetailError('Netwerkfout bij laden incident.');
      return;
    }

    populateDetail(detail);
  }

  function makeLoading() {
    const p = document.createElement('p');
    p.className = 'ai-dashboard-dialog-section';
    p.textContent = 'Laden…';
    return p;
  }

  function renderDetailError(message) {
    const p = document.createElement('p');
    p.className = 'ai-dashboard-dialog-error';
    p.textContent = message;
    detailBodyEl.replaceChildren(p);
  }

  function populateDetail(detail) {
    const envelope = detail && typeof detail === 'object' ? detail.envelope : null;
    const inner = envelope && typeof envelope === 'object' ? envelope.payload || {} : {};
    const diagnosis = inner && typeof inner.diagnosis === 'object' ? inner.diagnosis : null;
    const trace = Array.isArray(inner.tool_trace) ? inner.tool_trace : [];

    const service = detail.service || inner.service || '—';
    const time = formatClock(new Date(((detail.received_at || 0)) * 1000));
    detailTitleEl.textContent = 'Incident #' + detail.id + ' — ' + service + ' @ ' + time;
    detailDialog.dataset.severity = (detail.severity || 'info').toLowerCase();

    detailMetaEl.replaceChildren();
    detailMetaEl.appendChild(metaItem('Type', humanizeEventType(detail.event_type)));
    detailMetaEl.appendChild(metaPillItem('Severity', makeBadge('severity', detail.severity || 'unknown')));
    if (detail.confidence) {
      detailMetaEl.appendChild(metaPillItem('Confidence', makeBadge('confidence', detail.confidence)));
    }
    if (detail.correlation_id) {
      detailMetaEl.appendChild(metaItem('Correlation', detail.correlation_id));
    }
    const pipelineMs = computePipelineMs(inner);
    if (pipelineMs !== null) {
      detailMetaEl.appendChild(metaItem('Pipeline', (pipelineMs / 1000).toFixed(1) + 's'));
    }

    detailBodyEl.replaceChildren();

    if (diagnosis) {
      appendTextSection('Root cause', diagnosis.root_cause);
      appendTextSection('Critical failure', diagnosis.critical_failure);
      appendTextSection('Impact', diagnosis.impact, 'impact');
      appendTextSection('Suggested action', diagnosis.suggested_action, 'action');
    } else if (inner.reason) {
      appendTextSection('Skip reason', String(inner.reason));
    }

    appendFlowSection(trace);

    if (diagnosis && diagnosis.evidence_summary) {
      appendEvidenceSection(diagnosis.evidence_summary);
    }

    appendKibanaLink(detail, inner);
  }

  function metaItem(label, value) {
    const span = document.createElement('span');
    const strong = document.createElement('strong');
    strong.textContent = label + ':';
    span.appendChild(strong);
    span.appendChild(document.createTextNode(' ' + value));
    return span;
  }

  function metaPillItem(label, pillEl) {
    const span = document.createElement('span');
    const strong = document.createElement('strong');
    strong.textContent = label + ':';
    span.appendChild(strong);
    span.appendChild(document.createTextNode(' '));
    span.appendChild(pillEl);
    return span;
  }

  function appendKibanaLink(detail, inner) {
    const service = detail.service || (inner && inner.service);
    const receivedAt = Number(detail.received_at);
    if (!service || !Number.isFinite(receivedAt) || receivedAt <= 0) {
      return;
    }
    const safeService = String(service).replace(/[^A-Za-z0-9._-]/g, '');
    if (!safeService) {
      return;
    }
    const fromIso = new Date((receivedAt - KIBANA_WINDOW_PRE_SEC) * 1000).toISOString();
    const toIso = new Date((receivedAt + KIBANA_WINDOW_POST_SEC) * 1000).toISOString();
    const g = "(time:(from:'" + fromIso + "',to:'" + toIso + "'))";
    const a = "(index:'" + KIBANA_INDEX_PATTERN + "',query:(language:kuery,query:'" + KIBANA_SERVICE_FIELD + ':"' + safeService + "\"'))";
    const url = KIBANA_BASE_URL + '/app/discover#/?_g=' + encodeURI(g) + '&_a=' + encodeURI(a);

    const section = document.createElement('div');
    section.className = 'ai-dashboard-dialog-section ai-dashboard-dialog-kibana';
    const link = document.createElement('a');
    link.className = 'ai-dashboard-kibana-link';
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = 'Bekijk logs in Kibana ↗';
    section.appendChild(link);
    detailBodyEl.appendChild(section);
  }

  function appendTextSection(title, body, variant) {
    if (body === undefined || body === null || String(body).trim() === '') {
      return;
    }
    const section = document.createElement('div');
    section.className = 'ai-dashboard-dialog-section'
      + (variant ? ' ai-dashboard-dialog-section--card ai-dashboard-dialog-section--' + variant : '');
    const h = document.createElement('h4');
    h.className = 'ai-dashboard-dialog-section-title';
    h.textContent = title;
    section.appendChild(h);
    const p = document.createElement('p');
    p.textContent = String(body);
    section.appendChild(p);
    detailBodyEl.appendChild(section);
  }

  function appendEvidenceSection(text) {
    const section = document.createElement('div');
    section.className = 'ai-dashboard-dialog-section';
    const h = document.createElement('h4');
    h.className = 'ai-dashboard-dialog-section-title';
    h.textContent = 'Evidence summary';
    section.appendChild(h);
    const pre = document.createElement('pre');
    pre.className = 'ai-dashboard-dialog-evidence';
    pre.textContent = String(text);
    section.appendChild(pre);
    detailBodyEl.appendChild(section);
  }

  function appendFlowSection(trace) {
    const section = document.createElement('div');
    section.className = 'ai-dashboard-dialog-section';
    const h = document.createElement('h4');
    h.className = 'ai-dashboard-dialog-section-title';
    h.textContent = 'Tool flow';
    section.appendChild(h);

    const wrap = document.createElement('div');
    wrap.className = 'ai-dashboard-dialog-flow';
    if (trace.length === 0) {
      const empty = document.createElement('span');
      empty.className = 'ai-dashboard-dialog-flow-empty';
      empty.textContent = 'Geen tool-trace beschikbaar voor dit event.';
      wrap.appendChild(empty);
      section.appendChild(wrap);
      detailBodyEl.appendChild(section);
      return;
    }
    section.appendChild(wrap);
    detailBodyEl.appendChild(section);

    const dsl = buildMermaidFlowchart(trace);
    if (window.mermaid) {
      ensureMermaidInit();
      const renderId = 'ai-dashboard-mermaid-' + Math.random().toString(36).slice(2);
      window.mermaid.render(renderId, dsl).then((result) => {
        wrap.innerHTML = result.svg;
      }).catch((err) => {
        wrap.replaceChildren();
        const span = document.createElement('span');
        span.className = 'ai-dashboard-dialog-flow-empty';
        span.textContent = 'Diagram render mislukt: ' + (err && err.message ? err.message : 'unknown');
        wrap.appendChild(span);
      });
    } else {
      const span = document.createElement('span');
      span.className = 'ai-dashboard-dialog-flow-empty';
      span.textContent = 'Mermaid library nog niet geladen.';
      wrap.appendChild(span);
    }
  }

  function computePipelineMs(payload) {
    if (!payload || typeof payload !== 'object') {
      return null;
    }
    const a = payload.step_a && typeof payload.step_a.duration_ms === 'number' ? payload.step_a.duration_ms : 0;
    const b = payload.step_b && typeof payload.step_b.duration_ms === 'number' ? payload.step_b.duration_ms : 0;
    const total = a + b;
    return total > 0 ? total : null;
  }

  function humanizeEventType(t) {
    const map = {
      incident_diagnosed: 'Diagnose',
      incident_skipped: 'Overgeslagen',
      incident_circuit_open: 'Circuit open',
      incident_resolved: 'Opgelost',
    };
    return map[t] || t || '—';
  }

  function escapeMermaid(s) {
    return String(s == null ? '' : s).replace(/[\[\]{}|<>"'#:`()\\%;=\n\r]/g, '_');
  }

  function serverNodeId(server) {
    return 's_' + escapeMermaid(server).replace(/[^A-Za-z0-9_]/g, '_');
  }

  function buildMermaidFlowchart(trace) {
    const lines = ['flowchart TD'];
    lines.push('  user(("Heartbeat"))');
    lines.push('  master(["mcp-master"])');
    lines.push('  answer(("Diagnosis"))');
    lines.push('  user --> master');

    const servers = [...new Set(trace.map((t) => t.server || 'unknown'))];
    servers.forEach((server) => {
      const sid = serverNodeId(server);
      lines.push('  ' + sid + '(["' + escapeMermaid(server) + '"])');
    });

    const toolCounts = {};
    trace.forEach((e) => {
      const key = e.tool || '?';
      toolCounts[key] = (toolCounts[key] || 0) + 1;
    });
    const toolSeen = {};

    trace.forEach((entry) => {
      const key = entry.tool || '?';
      const sid = serverNodeId(entry.server || 'unknown');
      const tool = escapeMermaid(key);
      const ms = (entry.ms || 0) + 'ms';
      const status = entry.ok === false ? ' (failed)' : '';
      let prefix = '';
      if (toolCounts[key] > 1) {
        toolSeen[key] = (toolSeen[key] || 0) + 1;
        prefix = '#' + toolSeen[key] + ' ';
      }
      lines.push('  master -->|"' + prefix + tool + '<br/>' + ms + status + '"| ' + sid);
    });

    servers.forEach((server) => {
      const sid = serverNodeId(server);
      lines.push('  ' + sid + ' -.-> master');
    });

    lines.push('  master --> answer');

    trace.forEach((entry, idx) => {
      if (entry.ok === false) {
        lines.push('  linkStyle ' + (idx + 1) + ' stroke:#dc6464,stroke-width:2.5px');
      }
    });

    lines.push('  classDef boundary fill:#1d0b25,stroke:#a98cff,stroke-width:2px,color:#fff');
    lines.push('  class user,answer boundary');

    return lines.join('\n');
  }

  function ensureMermaidInit() {
    if (!window.mermaid || window.mermaid.__aiDashboardInit) {
      return;
    }
    window.mermaid.initialize({
      startOnLoad: false,
      securityLevel: 'strict',
      theme: 'base',
      themeVariables: {
        fontFamily: "'Inter', -apple-system, sans-serif",
        fontSize: '13px',
        primaryColor: '#6633ea',
        primaryTextColor: '#ffffff',
        primaryBorderColor: '#a98cff',
        lineColor: '#a98cff',
        secondaryColor: '#3a1f4f',
        tertiaryColor: '#2d1a4e',
        background: 'transparent',
        mainBkg: '#3a1f4f',
        secondBkg: '#2d1a4e',
        nodeBorder: '#a98cff',
        clusterBkg: '#1d0b25',
        clusterBorder: '#6633ea',
        edgeLabelBackground: '#1d0b25',
        labelTextColor: '#ffffff',
        nodeTextColor: '#ffffff',
        textColor: '#ffffff',
      },
      flowchart: {
        htmlLabels: false,
        curve: 'basis',
        padding: 16,
        nodeSpacing: 50,
        rankSpacing: 70,
        useMaxWidth: true,
      },
    });
    window.mermaid.__aiDashboardInit = true;
  }
})(Drupal, once);
