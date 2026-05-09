(function (Drupal, drupalSettings, once) {
  // Per-page conversation history. mcp-master is intentionally stateless on
  // /chat (see HTTP_API.md decision-log) — the client carries context. Lives
  // for the page session; lost on refresh by design (server-side persistence
  // is v2 work).
  const history = [];
  // Soft cap on turns to prevent unbounded request bodies in long sessions.
  // Anthropic enforces a hard token limit; this trims well before that.
  const MAX_HISTORY_TURNS = 40;

  Drupal.behaviors.jarvisChat = {
    attach(context) {
      once('jarvis-chat', '#jarvis-form', context).forEach((form) => {
        const input = form.querySelector('#jarvis-input');
        const button = form.querySelector('#jarvis-send');
        const convo = document.getElementById('jarvis-conversation');

        const chat = document.getElementById('jarvis-chat');

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const prompt = input.value.trim();
          if (!prompt) return;

          // First submit transitions the layout from empty-state
          // (centered greeting + floating input) to active-state
          // (unified chat card with conversation + pinned input).
          // We flip BEFORE adding the user bubble so the DOM-mutation
          // doesn't briefly render in empty-state coords.
          if (chat) {
            chat.classList.remove('is-empty');
          }

          const userTurn = { role: 'user', content: prompt };
          appendBubble(convo, 'user', prompt);
          input.value = '';
          button.disabled = true;
          // Loading bubble starts as a 3-dot typing indicator. CSS animates
          // the dots; once the real answer arrives, renderMarkdown's
          // replaceChildren() (or textContent on error) clears them.
          const loading = appendBubble(convo, 'assistant', '');
          loading.classList.add('jarvis-typing');
          for (let i = 0; i < 3; i += 1) {
            const dot = document.createElement('span');
            dot.className = 'dot';
            loading.appendChild(dot);
          }

          try {
            const res = await jarvisFetch('/api/jarvis/chat', {
              messages: [...history, userTurn],
            });
            const data = await res.json();
            if (res.ok) {
              // Mutate history only on success — a failed turn must not
              // pollute the conversation context for the retry.
              history.push(userTurn);
              // Strip the SETUP_PROMPT triple-backtick fence before storing —
              // Anthropic re-reading its own fenced turn treats it as a code
              // snippet and re-attempts prior tool-calls thinking the question
              // wasn't really answered.
              history.push({
                role: 'assistant',
                content: stripOuterCodeFence(data.answer || ''),
              });
              if (history.length > MAX_HISTORY_TURNS) {
                history.splice(0, history.length - MAX_HISTORY_TURNS);
              }
              renderMarkdown(loading, data.answer || '');
              if (Array.isArray(data.tool_trace) && data.tool_trace.length > 0) {
                appendApprovalCards(loading, data.tool_trace, convo);
                appendToolFlow(loading, data.tool_trace);
              }
            } else {
              loading.classList.remove('jarvis-typing');
              loading.textContent = `Fout: ${data.error || res.statusText}`;
            }
          } catch (err) {
            loading.classList.remove('jarvis-typing');
            loading.textContent = `Fout: ${err.message}`;
          } finally {
            button.disabled = false;
          }
        });
      });
    },
  };

  // CSRF + JSON POST helper — same dance for /chat, /chat/approve, /chat/reject.
  // Token fetched per-call rather than cached because Drupal's session token can
  // rotate; the cost is one extra GET (~5ms intra-network) and the benefit is no
  // 403-after-rotation surprises during long-lived chat sessions.
  async function jarvisFetch(path, body) {
    const csrf = await fetch('/session/token').then((r) => r.text());
    return fetch(path, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: JSON.stringify(body),
    });
  }

  // Iterate the trace, render one approval-card per status='pending' entry.
  // Cards live INSIDE the assistant bubble so they scroll naturally with the
  // conversation. Buttons capture the action_id via closure and POST to the
  // /chat/approve and /chat/reject Drupal proxy actions on click.
  function appendApprovalCards(bubble, trace, convo) {
    trace.forEach((entry) => {
      if (entry.status !== 'pending' || !entry.action_id) return;
      bubble.appendChild(buildApprovalCard(entry, convo));
    });
  }

  function buildApprovalCard(entry, convo) {
    const card = document.createElement('div');
    card.className = 'jarvis-approval-card';
    card.dataset.actionId = entry.action_id;

    const title = document.createElement('div');
    title.className = 'jarvis-approval-card__title';
    title.textContent = 'Goedkeuring vereist';
    card.appendChild(title);

    const meta = document.createElement('dl');
    meta.className = 'jarvis-approval-card__meta';
    appendMetaRow(meta, 'Tool', entry.tool || '?');
    appendMetaRow(meta, 'Server', entry.server || '?');
    appendMetaRow(meta, 'Action ID', entry.action_id, true);
    card.appendChild(meta);

    const reason = document.createElement('textarea');
    reason.className = 'jarvis-approval-card__reason';
    reason.rows = 2;
    reason.placeholder = 'Reden voor afwijzing (optioneel)';
    card.appendChild(reason);

    const actions = document.createElement('div');
    actions.className = 'jarvis-approval-card__actions';

    const approveBtn = document.createElement('button');
    approveBtn.type = 'button';
    approveBtn.className = 'jarvis-approve-btn';
    approveBtn.textContent = 'Goedkeuren';

    const rejectBtn = document.createElement('button');
    rejectBtn.type = 'button';
    rejectBtn.className = 'jarvis-reject-btn';
    rejectBtn.textContent = 'Afwijzen';

    const errorBox = document.createElement('div');
    errorBox.className = 'jarvis-approval-card__error';
    errorBox.hidden = true;

    approveBtn.addEventListener('click', async () => {
      await decideAction(card, errorBox, '/api/jarvis/chat/approve', { action_id: entry.action_id }, 'Goedgekeurd', convo);
    });
    rejectBtn.addEventListener('click', async () => {
      const r = reason.value.trim();
      const body = { action_id: entry.action_id };
      if (r !== '') body.reason = r;
      await decideAction(card, errorBox, '/api/jarvis/chat/reject', body, 'Afgewezen', convo);
    });

    actions.appendChild(approveBtn);
    actions.appendChild(rejectBtn);
    card.appendChild(actions);
    card.appendChild(errorBox);

    return card;
  }

  function appendMetaRow(dl, label, value, mono) {
    const dt = document.createElement('dt');
    dt.textContent = label;
    const dd = document.createElement('dd');
    if (mono) {
      const code = document.createElement('code');
      code.textContent = value;
      dd.appendChild(code);
    } else {
      dd.textContent = value;
    }
    dl.appendChild(dt);
    dl.appendChild(dd);
  }

  // Disable inputs while the request is in flight; on response, either lock
  // the card into a resolved state with status label + new assistant bubble
  // (2xx), or restore inputs with an inline error message (4xx/5xx) so the
  // user can retry — including 409 'already decided' for double-click cases.
  async function decideAction(card, errorBox, path, body, resolvedLabel, convo) {
    setCardBusy(card, true);
    errorBox.hidden = true;
    errorBox.textContent = '';
    let res;
    try {
      res = await jarvisFetch(path, body);
    } catch (err) {
      setCardBusy(card, false);
      showCardError(errorBox, `Fout: ${err.message}`);
      return;
    }
    let data = {};
    try { data = await res.json(); } catch { /* empty body */ }
    if (!res.ok) {
      setCardBusy(card, false);
      showCardError(errorBox, `Fout: ${data.error || res.statusText}`);
      return;
    }
    finalizeCard(card, resolvedLabel);
    appendDecisionBubble(convo, data.answer || '');
  }

  function setCardBusy(card, busy) {
    card.classList.toggle('is-pending-decision', busy);
    card.querySelectorAll('button, textarea').forEach((el) => {
      el.disabled = busy;
    });
  }

  function showCardError(errorBox, msg) {
    errorBox.textContent = msg;
    errorBox.hidden = false;
  }

  // Final card state — keep the meta-rows visible (so the action stays
  // auditable in the conversation) but swap the action area for a status
  // label. is-resolved class also dims the whole card via CSS.
  function finalizeCard(card, statusLabel) {
    card.classList.add('is-resolved');
    card.classList.remove('is-pending-decision');
    const actions = card.querySelector('.jarvis-approval-card__actions');
    const reason = card.querySelector('.jarvis-approval-card__reason');
    if (reason) reason.remove();
    if (actions) {
      const status = document.createElement('span');
      status.className = 'jarvis-approval-card__status';
      status.dataset.label = statusLabel;
      status.textContent = statusLabel;
      actions.replaceChildren(status);
    }
  }

  // After approve/reject the dispatched-tool-result (or rejection notice)
  // arrives as data.answer — render it as a regular assistant bubble so the
  // conversation reads chronologically + push to history so a follow-up
  // turn includes the resolved action's outcome in Anthropic's context.
  function appendDecisionBubble(convo, answer) {
    const bubble = appendBubble(convo, 'assistant', '');
    renderMarkdown(bubble, answer);
    history.push({ role: 'assistant', content: stripOuterCodeFence(answer) });
    if (history.length > MAX_HISTORY_TURNS) {
      history.splice(0, history.length - MAX_HISTORY_TURNS);
    }
  }

  // mcp-master wraps every answer in a triple-backtick code fence per
  // `prompts.rs::SETUP_PROMPT` (Teams renders nicer that way). Browser-side
  // we strip the outer fence so headings/lists render as real elements
  // instead of monospace text. Inner code blocks survive — the regex is
  // anchored to start/end-of-string and uses non-greedy match.
  function stripOuterCodeFence(md) {
    const trimmed = md.trim();
    const match = trimmed.match(/^```(?:\w+)?\n([\s\S]*?)\n?```$/);
    return match ? match[1] : md;
  }

  // mcp-master returns LLM-authored markdown that may contain hostile
  // <script>/onerror. DOMPurify with RETURN_DOM_FRAGMENT strips those at
  // parse time and yields a safe DocumentFragment we can splice in via
  // replaceChildren — avoids any string-stage HTML write.
  function renderMarkdown(target, markdown) {
    const unwrapped = stripOuterCodeFence(markdown);
    const rawHtml = window.marked.parse(unwrapped);
    const fragment = window.DOMPurify.sanitize(rawHtml, {
      RETURN_DOM_FRAGMENT: true,
    });
    target.classList.remove('jarvis-typing');
    target.replaceChildren(fragment);
  }

  function appendBubble(convo, role, text) {
    const div = document.createElement('div');
    div.className = `jarvis-bubble jarvis-${role}`;
    div.textContent = text;
    convo.appendChild(div);
    convo.scrollTop = convo.scrollHeight;
    return div;
  }

  // Render mcp-master's per-call trace as a clickable button under the
  // bubble; click opens a Mermaid flowchart in a native <dialog>. Trace
  // strings flow through escapeMermaid + Mermaid's strict securityLevel,
  // so a hypothetical server-side bug that puts user-shaped data in
  // tool/server names can't escape into HTML or SVG injection.
  function appendToolFlow(bubble, trace) {
    const totalMs = trace.reduce((sum, t) => sum + (t.ms || 0), 0);
    const failedCount = trace.filter((t) => t.ok === false).length;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'jarvis-tool-flow-trigger';
    trigger.textContent = failedCount > 0
      ? `View flow: ${trace.length} tools (${totalMs}ms) — ${failedCount} failed`
      : `View flow: ${trace.length} tools (${totalMs}ms)`;

    const dialog = document.createElement('dialog');
    dialog.className = 'jarvis-tool-flow-dialog';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'jarvis-tool-flow-close';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => dialog.close());
    dialog.appendChild(closeBtn);

    const heading = document.createElement('h3');
    heading.className = 'jarvis-tool-flow-heading';
    heading.textContent = 'Tool flow';
    dialog.appendChild(heading);

    const diagram = document.createElement('div');
    diagram.className = 'jarvis-tool-flow-diagram';
    dialog.appendChild(diagram);

    const dsl = buildMermaidFlowchart(trace);

    trigger.addEventListener('click', async () => {
      if (!diagram.dataset.rendered && window.mermaid) {
        ensureMermaidInit();
        try {
          const id = `jarvis-mermaid-${Math.random().toString(36).slice(2)}`;
          const { svg } = await window.mermaid.render(id, dsl);
          // Mermaid securityLevel:'strict' already escapes text and strips
          // event-handlers; routing the SVG through DOMPurify on top
          // collapsed <text>/<tspan> nodes and rendered empty boxes.
          diagram.innerHTML = svg;
          diagram.dataset.rendered = 'true';
        } catch (err) {
          diagram.textContent = `Diagram render failed: ${err.message}`;
        }
      }
      dialog.showModal();
    });

    bubble.appendChild(trigger);
    bubble.appendChild(dialog);
    const convo = bubble.parentElement;
    if (convo) convo.scrollTop = convo.scrollHeight;
  }

  // Mermaid uses its own DSL; characters like [ ] { } | < > # " ' would
  // either break parsing or open a syntax-injection seam. Replace them
  // with underscore — defensive even though server-side strings are
  // currently controlled.
  function escapeMermaid(s) {
    return String(s == null ? '' : s).replace(/[\[\]{}|<>"'#:`()\\]/g, '_');
  }

  // Stable id per server — Mermaid node-ids must match [A-Za-z0-9_].
  function serverNodeId(server) {
    return 's_' + escapeMermaid(server).replace(/[^A-Za-z0-9_]/g, '_');
  }

  function buildMermaidFlowchart(trace) {
    const lines = ['flowchart TD'];
    lines.push('  user(("User"))');
    lines.push('  master(["mcp-master"])');
    lines.push('  answer(("Answer"))');
    lines.push('  user --> master');

    const servers = [...new Set(trace.map((t) => t.server || 'unknown'))];
    servers.forEach((server) => {
      const sid = serverNodeId(server);
      lines.push(`  ${sid}(["${escapeMermaid(server)}"])`);
    });

    // Number duplicate tool-calls so temporal ordering is readable;
    // single-call tools stay un-numbered for cleanliness.
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
      const ms = `${entry.ms || 0}ms`;
      const status = entry.ok === false ? ' (failed)' : '';
      let prefix = '';
      if (toolCounts[key] > 1) {
        toolSeen[key] = (toolSeen[key] || 0) + 1;
        prefix = `#${toolSeen[key]} `;
      }
      lines.push(`  master -->|"${prefix}${tool}<br/>${ms}${status}"| ${sid}`);
    });

    // Dashed return-arrows: tool results flow back to mcp-master (not
    // straight to Answer), then mcp-master synthesizes the final reply.
    // One return per server, not per call, to keep the chart legible.
    servers.forEach((server) => {
      const sid = serverNodeId(server);
      lines.push(`  ${sid} -.-> master`);
    });

    lines.push('  master --> answer');

    // Edge index map (DSL definition order):
    //   0                       user --> master
    //   1..N                    master -->|...| serverX  (N = trace.length)
    //   N+1..N+S                serverX -.-> master      (S = servers.length)
    //   N+S+1                   master --> answer
    // Failed tool-edges live at indices 1..N.
    trace.forEach((entry, idx) => {
      if (entry.ok === false) {
        lines.push(`  linkStyle ${idx + 1} stroke:#dc6464,stroke-width:2.5px`);
      }
    });

    lines.push('  classDef boundary fill:#1d0b25,stroke:#a98cff,stroke-width:2px,color:#fff');
    lines.push('  class user,answer boundary');

    return lines.join('\n');
  }

  function ensureMermaidInit() {
    if (!window.mermaid || window.mermaid.__jarvisInit) return;
    window.mermaid.initialize({
      startOnLoad: false,
      // 'strict' disables raw-HTML in node labels — required because we
      // splice server-controlled strings (tool/server names) into the DSL.
      securityLevel: 'strict',
      theme: 'base',
      themeVariables: {
        fontFamily: "'Inter', -apple-system, sans-serif",
        fontSize: '14px',
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
        padding: 20,
        nodeSpacing: 60,
        rankSpacing: 80,
        useMaxWidth: true,
      },
    });
    window.mermaid.__jarvisInit = true;
  }
})(Drupal, drupalSettings, once);
