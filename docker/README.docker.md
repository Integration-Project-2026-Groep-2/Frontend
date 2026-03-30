# Docker Setup — Drupal + RabbitMQ Integration

## Project layout

Place the Docker files in your **existing Drupal project root** like this:

```
your-drupal-project/
├── frontend/
│   ├── Dockerfile
│   ├── docker-compose.dev.yml
│   ├── docker-compose.prod.yml
│   ├── .env.example                  ← copy to .env for prod
│   ├── Makefile
│   ├── settings.docker.php           ← copy to web/sites/default/
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   ├── php.ini
│   │   ├── php.prod.ini
│   │   ├── php-fpm.conf
│   │   └── xdebug.ini
│   └── supervisor/
│       ├── supervisord.dev.conf
│       └── supervisord.prod.conf
├── modules/
│   └── custom/
│       └── rabbitmq_integration/     ← your custom module
├── web/
│   └── sites/default/
│       ├── settings.php
│       └── settings.docker.php       ← copied from frontend/
├── composer.json
└── composer.lock
```

---

## First-time setup

### 1. Add the php-amqplib dependency

```bash
composer require php-amqplib/php-amqplib:^3.6
```

### 2. Place the settings file

```bash
cp frontend/settings.docker.php web/sites/default/settings.docker.php
```

Then add to the **bottom** of `web/sites/default/settings.php`:

```php
if (file_exists($app_root . '/' . $site_path . '/settings.docker.php')) {
  include $app_root . '/' . $site_path . '/settings.docker.php';
}
```

### 3. Add to .gitignore

```
frontend/.env
web/sites/default/settings.docker.php
web/sites/default/files/
private/
```

---

## Development

```bash
# Start everything (Drupal, Nginx, MariaDB, RabbitMQ, phpMyAdmin)
make dev-up

# Install Drupal (first time only)
make install

# Enable the RabbitMQ module
make enable-module

# Clear caches
make cr

# Open a shell in the Drupal container
make dev-shell

# Test the RabbitMQ connection
make test-rmq
```

| Service    | URL                          | Credentials        |
|------------|------------------------------|--------------------|
| Drupal     | http://localhost:8080        | admin / admin      |
| phpMyAdmin | http://localhost:8081        | drupal / drupal    |
| RabbitMQ   | http://localhost:15672       | drupal / drupal    |

The **RabbitMQ consumer daemon** starts automatically inside the Drupal container via Supervisor. You don't need to run it manually — check its logs with:

```bash
make dev-logs
```

---

## Production

### 1. Create your .env file

```bash
cp frontend/.env.example frontend/.env
# Edit frontend/.env — fill in real passwords
```

### 2. Build and start

```bash
make prod-build
make prod-up
```

### 3. Run Drupal database install / update

```bash
docker compose -f frontend/docker-compose.prod.yml exec -u www-data drupal \
  vendor/bin/drush updatedb -y && vendor/bin/drush cr
```

### Accessing RabbitMQ management UI in production

The management port (15672) is **not exposed** in production. Use an SSH tunnel:

```bash
ssh -L 15672:localhost:15672 user@your-server
# Then open http://localhost:15672 in your browser
```

---

## How the consumer runs

Supervisor runs two processes inside the single Drupal container:

```
supervisord
  ├── php-fpm        ← serves HTTP requests via Nginx
  └── rabbitmq-consumer  ← drush rabbitmq:consume (restarts every hour + on crash)
```

The consumer restarts automatically if it crashes. In both dev and prod it runs with `--time-limit=3600`, meaning it gracefully exits and restarts every hour — this prevents memory leaks from long-running PHP processes.

---

## Environment variables reference

| Variable           | Default     | Description                        |
|--------------------|-------------|------------------------------------|
| `DRUPAL_DB_HOST`   | `db`        | Database hostname                  |
| `DRUPAL_DB_NAME`   | `drupal`    | Database name                      |
| `DRUPAL_DB_USER`   | `drupal`    | Database username                  |
| `DRUPAL_DB_PASSWORD` | `drupal`  | Database password                  |
| `RABBITMQ_HOST`    | `rabbitmq`  | RabbitMQ hostname                  |
| `RABBITMQ_PORT`    | `5672`      | RabbitMQ AMQP port                 |
| `RABBITMQ_USER`    | `drupal`    | RabbitMQ username                  |
| `RABBITMQ_PASS`    | `drupal`    | RabbitMQ password                  |
| `RABBITMQ_VHOST`   | `/`         | RabbitMQ virtual host              |
| `DRUPAL_HASH_SALT` | *(none)*    | Drupal hash salt (set in prod!)    |
| `APP_ENV`          | *(none)*    | Set to `dev` to disable caches     |
