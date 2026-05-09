# Jarvis Chat

Admin-facing chatbot UI. Renders `/jarvis` as a chat page that proxies
`POST /api/jarvis/chat` to the `mcp-master` AI backend (Rust agent in the
`Integration-Project-2026-Groep-2/mcp-master` repo).

## What it does

- `GET /jarvis` — renders a chat widget (textarea + send + scrolling
  conversation area)
- `POST /api/jarvis/chat` — Drupal-side proxy to `http://mcp-master:8080/chat`
  with CSRF + permission gating
- `POST /api/jarvis/chat/approve` and `POST /api/jarvis/chat/reject` —
  Drupal-side proxies for R2 actionable-agent write-tool approval-flow
  (see [R2_APPROVAL_CARD.md](R2_APPROVAL_CARD.md))
- Markdown rendering of LLM answers via `marked` + `DOMPurify` (XSS-safe)
- Multi-turn conversation: previous user/assistant turns are included with
  every request so follow-up questions ("geef me daar het volledige object
  van") keep their context
- **R2 approval-card UI** — when mcp-master returns `tool_trace[i].status="pending"`,
  the assistant bubble renders a Goedkeuren/Afwijzen card. Approval
  dispatches the proposed write-tool against Salesforce (via mcp-master
  → CRM-MCP → SF + `contact.topic` AMQP broadcast); reject discards it
  with an optional reason

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
docker compose exec frontend drush cache:rebuild
```

`hook_install` (in `jarvis_chat.install`) auto-grants `'use jarvis chat'`
to `administrator` and `event_manager` roles — no manual `drush
role:perm:add` step needed. On existing deploys, run `drush updb -y` to
trigger `hook_update_8001` which performs the same grant idempotently.

After enabling, log in as a user with `administrator` or `event_manager`
role and visit `/jarvis`. Visitors and non-elevated users get a 403 from
both the Drupal route-permission check **and** the controller's
`assertElevatedRole()` defense-in-depth gate.

## Configure

| Env var | Default | Purpose |
|---|---|---|
| `MCP_MASTER_URL` | `http://mcp-master:8080` | mcp-master backend endpoint. Override naar `http://host.docker.internal:8080` voor lokale dev. |
| `CHAT_JWT_SECRET` | _unset_ | Shared HS256 secret for R2 JWT-minting. Generate with `openssl rand -hex 32` and set **same value** on mcp-master container. Without this, approve/reject return 403; legacy `/chat` falls back to `MCP_MASTER_BEARER_TOKEN`. |
| `MCP_MASTER_BEARER_TOKEN` | _unset_ | Static bearer fallback for transitional deploys (used only when `CHAT_JWT_SECRET` is unset). mcp-master in skip-warn mode accepteert ANY non-empty waarde. |

```yaml
# compose.yml fragment
services:
  frontend:
    environment:
      - MCP_MASTER_URL=http://host.docker.internal:8080
      - CHAT_JWT_SECRET=${CHAT_JWT_SECRET:-}
      - MCP_MASTER_BEARER_TOKEN=${MCP_MASTER_BEARER_TOKEN:-}
```

JWT-minting flow: Drupal mints a JWT per-call met `sub=currentUser->id()`,
`scope=read+act`, `exp=+1h`. mcp-master's `AuthScope` extractor decodes het
en bindt het aan `PendingAction.user_id` voor proposer-equals-confirmer
enforcement (zie `R2_APPROVAL_CARD.md` voor de volledige flow).

## Network requirement (production)

The `frontend` Drupal container must join `infra_rabbitmq_net` to reach the
`mcp-master` service by docker-DNS. Coordinate with Infra — see
`mcp-master/.claude/rules/INFRA_DEPLOY_HANDOVER.md` for the centrale-compose
options.

## Tests

```bash
docker compose exec frontend vendor/bin/phpunit \
  -c web/core/phpunit.xml.dist \
  --bootstrap web/core/tests/bootstrap.php \
  web/modules/custom/custom/jarvis_chat/tests
```

18 PHPUnit unit tests cover the proxy controller:
- Prompt validation, multi-turn forward, upstream-failure → 502, response
  whitelist (drops tokens/iterations/correlation_id)
- R2: approve forwards action_id + Authorization Bearer header,
  missing/non-string action_id → 400, upstream 409 surfaces, reject reason
  forwarded, approve response whitelist drops extra fields
- Auth: JWT primary path, MCP_MASTER_BEARER_TOKEN fallback, no header when
  neither configured
- Role-gate: visitor/authenticated-only → 403 on chat/approve/reject;
  administrator + event_manager → 200 on chat

## Local dev

1. `docker compose up -d`
2. (One-time) install Drupal via http://localhost:8080 if not already done
3. `drush en jarvis_chat -y && drush role:perm:add administrator 'use jarvis chat'`
4. (Optional) Run mcp-master in parallel: `cargo run -- --server-mode` in the
   `mcp-master` repo, plus `MCP_MASTER_URL=http://host.docker.internal:8080`
   on the frontend container

## See also

- [R2_APPROVAL_CARD.md](R2_APPROVAL_CARD.md) — module-side spec for the
  actionable-agent flow (JWT signer, JS rendering, error humanisation)
- Backend contract: `mcp-master/.claude/rules/HTTP_API.md` (especially
  §1.5 Approval-flow)
- Deploy handover: `mcp-master/.claude/rules/R2_DEPLOY_HANDOVER.md`
- AI-team architecture: `mcp-master/.claude/rules/ai-team-architecture.md`
