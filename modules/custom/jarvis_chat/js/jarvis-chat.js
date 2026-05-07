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
            const csrf = await fetch('/session/token').then((r) => r.text());
            const res = await fetch('/api/jarvis/chat', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
              },
              body: JSON.stringify({ messages: [...history, userTurn] }),
            });
            const data = await res.json();
            if (res.ok) {
              // Mutate history only on success — a failed turn must not
              // pollute the conversation context for the retry.
              history.push(userTurn);
              history.push({ role: 'assistant', content: data.answer || '' });
              if (history.length > MAX_HISTORY_TURNS) {
                history.splice(0, history.length - MAX_HISTORY_TURNS);
              }
              renderMarkdown(loading, data.answer || '');
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
})(Drupal, drupalSettings, once);
