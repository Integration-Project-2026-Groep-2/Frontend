(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.jarvisChat = {
    attach(context) {
      once('jarvis-chat', '#jarvis-form', context).forEach((form) => {
        const input = form.querySelector('#jarvis-input');
        const button = form.querySelector('#jarvis-send');
        const convo = document.getElementById('jarvis-conversation');

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const prompt = input.value.trim();
          if (!prompt) return;

          appendBubble(convo, 'user', prompt);
          input.value = '';
          button.disabled = true;
          const loading = appendBubble(convo, 'assistant', '...');

          try {
            const csrf = await fetch('/session/token').then((r) => r.text());
            const res = await fetch('/api/jarvis/chat', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
              },
              body: JSON.stringify({ prompt }),
            });
            const data = await res.json();
            if (res.ok) {
              renderMarkdown(loading, data.answer || '');
            } else {
              loading.textContent = `Fout: ${data.error || res.statusText}`;
            }
          } catch (err) {
            loading.textContent = `Fout: ${err.message}`;
          } finally {
            button.disabled = false;
          }
        });
      });
    },
  };

  // mcp-master returns LLM-authored markdown that may contain hostile
  // <script>/onerror. DOMPurify with RETURN_DOM_FRAGMENT strips those at
  // parse time and yields a safe DocumentFragment we can splice in via
  // replaceChildren — avoids any string-stage HTML write.
  function renderMarkdown(target, markdown) {
    const rawHtml = window.marked.parse(markdown);
    const fragment = window.DOMPurify.sanitize(rawHtml, {
      RETURN_DOM_FRAGMENT: true,
    });
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
