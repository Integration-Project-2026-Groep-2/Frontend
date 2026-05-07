# Jarvis Chat

Admin-facing chatbot UI. Renders `/jarvis` as a chat page that proxies
`POST /api/jarvis/chat` to the `mcp-master` AI backend (Rust agent in the
`Integration-Project-2026-Groep-2/mcp-master` repo).

## What it does

- `GET /jarvis` — renders a chat widget (textarea + send + scrolling
  conversation area)
- `POST /api/jarvis/chat` — Drupal-side proxy to `http://mcp-master:8080/chat`
  with CSRF + permission gating
- Markdown rendering of LLM answers via `marked` + `DOMPurify` (XSS-safe)
- Multi-turn conversation: previous user/assistant turns are included with
  every request so follow-up questions ("geef me daar het volledige object
  van") keep their context

## Multi-turn behavior

History lives in **JS module-scope memory**, lost on page refresh. mcp-master
itself stays stateless on `/chat` (per `HTTP_API.md` decision-log) — the
client carries the conversation context via the `messages` array in each POST.

Implications:

- **Refresh = new conversation**. Acceptable for v1 demo. Server-side
  persistence (Postgres + `session_id`) is on the v2 roadmap.
- **History caps at 40 turns** (FIFO trim). Anthropic enforces a hard token
  limit; the soft cap trims well before that.
- **Failed POST does not pollute history**. A 5xx upstream error leaves the
  history unchanged so the user can retry the same question without a
  corrupted turn-pair.

## Install

```bash
docker compose exec frontend drush en jarvis_chat -y
docker compose exec frontend drush role:perm:add administrator 'use jarvis chat'
docker compose exec frontend drush cache:rebuild
```

After enabling, log in as a user with the `administrator` role and visit
`/jarvis`. Anonymous and non-admin users get a 403.

## Configure

`MCP_MASTER_URL` (env var on the `frontend` container) — defaults to
`http://mcp-master:8080`. Override for local dev:

```yaml
# compose.yml fragment
services:
  frontend:
    environment:
      - MCP_MASTER_URL=http://host.docker.internal:8080
```

## Network requirement (production)

The `frontend` Drupal container must join `infra_rabbitmq_net` to reach the
`mcp-master` service by docker-DNS. Coordinate with Infra — see
`mcp-master/.claude/rules/INFRA_DEPLOY_HANDOVER.md` for the centrale-compose
options.

## Tests

```bash
docker compose exec frontend vendor/bin/phpunit \
  modules/custom/jarvis_chat
```

Three PHPUnit unit tests cover the proxy controller: empty prompt → 400,
upstream Guzzle failure → 502, valid response → 200 with answer body.

## Local dev

1. `docker compose up -d`
2. (One-time) install Drupal via http://localhost:8080 if not already done
3. `drush en jarvis_chat -y && drush role:perm:add administrator 'use jarvis chat'`
4. (Optional) Run mcp-master in parallel: `cargo run -- --server-mode` in the
   `mcp-master` repo, plus `MCP_MASTER_URL=http://host.docker.internal:8080`
   on the frontend container

## See also

- Backend contract: `mcp-master/.claude/rules/HTTP_API.md`
- AI-team architecture: `mcp-master/.claude/rules/ai-team-architecture.md`
