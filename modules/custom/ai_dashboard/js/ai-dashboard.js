(function (Drupal, once) {
  'use strict';

  const SERVICES = ['kassa', 'crm', 'controlroom', 'frontend', 'mailing', 'facturatie', 'planning', 'iot'];
  const POLL_MS = 30000;
  const DOWN_WINDOW_MS = 5 * 60 * 1000;

  const downSince = new Map();

  let csrfToken = null;
  let lastSeenTs = 0;
  let pollStatusEl = null;
  let pillsEl = null;
  let rowsEl = null;

  Drupal.behaviors.aiDashboard = {
    attach: function (context) {
      once('ai-dashboard-init', '#ai-dashboard-app', context).forEach(function (root) {
        pollStatusEl = root.querySelector('#ai-dashboard-poll-status');
        pillsEl = root.querySelector('#ai-dashboard-pills');
        rowsEl = root.querySelector('#ai-dashboard-rows');
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
      for (const inc of incidents.slice().reverse()) {
        prependRow(inc);
        if (typeof inc.received_at === 'number' && inc.received_at > lastSeenTs) {
          lastSeenTs = inc.received_at;
        }
        if (inc.event_type === 'incident_diagnosed' && inc.service) {
          downSince.set(inc.service, Date.now());
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
      incident_diagnosed: ['diagnosed', 'diagnosed'],
      incident_skipped: ['skipped', 'skipped'],
      incident_circuit_open: ['circuit_open', 'circuit'],
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
})(Drupal, once);
