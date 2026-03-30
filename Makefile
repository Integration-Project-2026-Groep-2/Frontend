# ── Makefile — Docker shortcuts ───────────────────────────────────────────────
# Usage: make <target>

COMPOSE_DEV  = docker compose -f frontend/docker-compose.dev.yml
COMPOSE_PROD = docker compose -f frontend/docker-compose.prod.yml --env-file frontend/.env

.PHONY: help dev-up dev-down dev-logs prod-up prod-down drush cr install test-rmq

help:
	@echo ""
	@echo "  Dev commands:"
	@echo "    make dev-up       Start all dev containers"
	@echo "    make dev-down     Stop and remove dev containers"
	@echo "    make dev-logs     Tail logs for all dev containers"
	@echo "    make dev-build    Rebuild the dev image"
	@echo ""
	@echo "  Drupal commands (dev):"
	@echo "    make drush CMD='cr'           Run any drush command"
	@echo "    make cr                       Clear Drupal caches"
	@echo "    make install                  Run Drupal install"
	@echo "    make enable-module            Enable rabbitmq_integration"
	@echo ""
	@echo "  RabbitMQ commands (dev):"
	@echo "    make test-rmq                 Publish a test message"
	@echo "    make rmq-consume              Start consumer manually"
	@echo ""
	@echo "  Prod commands:"
	@echo "    make prod-up                  Start production stack"
	@echo "    make prod-down                Stop production stack"
	@echo "    make prod-build               Build production image"
	@echo ""

# ── Development ───────────────────────────────────────────────────────────────

dev-up:
	$(COMPOSE_DEV) up -d
	@echo "✅ Dev stack running."
	@echo "   Drupal:     http://localhost:8080"
	@echo "   phpMyAdmin: http://localhost:8081"
	@echo "   RabbitMQ:   http://localhost:15672  (user: drupal / drupal)"

dev-down:
	$(COMPOSE_DEV) down

dev-logs:
	$(COMPOSE_DEV) logs -f

dev-build:
	$(COMPOSE_DEV) build --no-cache drupal

dev-shell:
	$(COMPOSE_DEV) exec drupal bash

# ── Drupal shortcuts ──────────────────────────────────────────────────────────

drush:
	$(COMPOSE_DEV) exec -u www-data drupal vendor/bin/drush $(CMD)

cr:
	$(COMPOSE_DEV) exec -u www-data drupal vendor/bin/drush cr

install:
	$(COMPOSE_DEV) exec -u www-data drupal vendor/bin/drush site:install \
	  --db-url=mysql://drupal:drupal@db/drupal \
	  --account-name=admin \
	  --account-pass=admin \
	  -y

enable-module:
	$(COMPOSE_DEV) exec -u www-data drupal vendor/bin/drush en rabbitmq_integration -y
	$(COMPOSE_DEV) exec -u www-data drupal vendor/bin/drush cr

# ── RabbitMQ shortcuts ────────────────────────────────────────────────────────

test-rmq:
	$(COMPOSE_DEV) exec -u www-data drupal vendor/bin/drush rabbitmq:test-publish user.registered

rmq-consume:
	$(COMPOSE_DEV) exec -u www-data drupal vendor/bin/drush rabbitmq:consume

# ── Production ────────────────────────────────────────────────────────────────

prod-build:
	$(COMPOSE_PROD) build --no-cache drupal

prod-up:
	$(COMPOSE_PROD) up -d

prod-down:
	$(COMPOSE_PROD) down

prod-logs:
	$(COMPOSE_PROD) logs -f
