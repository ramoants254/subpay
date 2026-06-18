docker compose pull
docker compose up -d
docker compose exec app php artisan migrate --force
*** Begin Optimized README ***
# SubPay

Lightweight M-Pesa subscription billing engine built with Laravel 11 and PHP 8.4.

SubPay automates subscription lifecycle management (plans, trials, billing), executes Lipa‑na‑M‑Pesa STK Push charges via Safaricom Daraja, reconciles pending transactions, retries failed attempts, and queues transactional receipts.

---

## Features

- Subscription plans, trials, and grace periods
- Per-minute scheduler for high-frequency billing
- Idempotent charge creation and strict retry pipeline
- Reconciliation against Daraja Query API for missing callbacks
- Queue-based background workers and transactional email receipts
- Dockerized for development and production

## Tech snapshot

- Laravel 11, PHP 8.4
- PostgreSQL 16 (UUID primary keys)
- Redis for cache & queues
- Docker / docker-compose for local development
- CI: GitHub Actions (Pint, PHPStan, Pest)

---

## Quick start (local)

Prerequisites: Docker and Docker Compose installed.

1) Start containers

```bash
docker compose up -d
```

2) Install PHP dependencies and build assets (inside the `app` service)

```bash
docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app npm ci
docker compose exec app npm run build
```

3) Environment, keys, migrate & seed

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
```

4) Run tests

```bash
docker compose exec app ./vendor/bin/pest
```

---

## Important environment variables

Copy `.env.example` to `.env` and set these at minimum:

- `APP_KEY`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PASSWORD`
- `DAR_A_SHORTCODE`, `DAR_A_PASSKEY`, `DAR_A_ENV` (Daraja credentials)
- `MAIL_*` settings for transactional receipts

Sensitive production secrets should be provided via your CI/CD or container orchestrator — do not commit them.

---

## Scheduler & workers

- The minute-runner should run every minute to dispatch scheduled billing jobs. In production run:

```bash
docker compose exec -T app php artisan schedule:run
```

- Queue workers process background jobs (billing retries, reconciliation, receipts):

```bash
docker compose exec -T app php artisan queue:work --sleep=3 --tries=3
```

---

## Database notes

- Models use UUID primary keys for security and idempotency.
- `subscriptions.next_billing_at` is the scheduler index used by the minute-runner.
- `charges` include an `idempotency_key` and `checkout_request_id` to protect against duplicate collections.

---

## Deployment (high level)

- CI builds a slim production image and pushes it to your container registry (ECR in the reference pipeline).
- On the host, pull the image and run `docker compose up -d`, then run migrations with `php artisan migrate --force`.
- Ensure the scheduler runs every minute on the host and that queue workers are supervised.

---

## Observability

- Tail application logs for real-time debugging:

```bash
docker compose exec app tail -f /var/www/app/storage/logs/laravel.log
```

- Inspect queue logs:

```bash
docker compose logs --tail=100 -f queue
```

---

## Contributing

1. Fork the repository
2. Create a branch: `feature/your-change`
3. Run linters and tests: `composer test` / `./vendor/bin/pest`
4. Open a PR with a clear description of changes

---

## License

MIT — see the `LICENSE` file.

*** End Optimized README ***