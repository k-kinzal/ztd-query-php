# Harbor Symfony

A Symfony 8.1 community web application and API. Doctrine maps communities, memberships, boards, topics, posts, revisions, reports, restrictions, subscriptions, reactions, inbox notifications, publication receipts, audit records and digest deliveries. The publishing lifecycle is a Symfony Workflow state machine, and access control is enforced through Security voters and shared permission checks.

The application uses typed request DTOs with Serializer and Validator, Twig pages and a CSRF-protected topic form, account verification with Mailer, login throttling and publishing rate limits, optimistic entity versions, Messenger delivery with retry and failure transports, Scheduler tasks, shared digest locks and cached scheduler state.

## Install

PHP 8.4.1+, PDO SQLite, mbstring, intl and the normal Symfony extensions are required.

```sh
composer install --optimize-autoloader
# Store a generated secret and deployment settings in .env.local.
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
mkdir -p var
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports
php -S 127.0.0.1:8002 -t public public/router.php
```

Set `APP_SECRET` to the generated secret. `DATABASE_URL` defaults to a SQLite database in `var/`; the committed migration deliberately targets SQLite. `MAILER_DSN=null://null` is useful locally; configure SMTP and `MAIL_FROM` for verification and digests. `APP_URL` must match the public origin so verification links are correct.

For deployment, use `APP_ENV=prod`, `APP_DEBUG=0`, a secret of at least 32 characters, HTTPS and a web server serving only `public/`. The kernel refuses production startup with a missing or short secret. Keep `var/` writable and the database outside the document root. The default lock store is local: run digest workers on the same host or configure a shared lock store before distributing them.

```sh
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
php bin/console messenger:consume scheduler_community --time-limit=3600
php bin/console messenger:failed:show
php bin/console messenger:failed:retry
```

Supervise and restart both consumers. Scheduler state is persisted in `cache.scheduler`. It escalates reports every 15 minutes, prunes retained metadata at 03:10 UTC, and schedules yesterday's digests at 07:00 UTC. Configure shared scheduler storage if deploying multiple scheduler consumers.

## Accounts and API

`GET /api/session` creates a session and returns the API CSRF token. Retain the cookie and send `X-CSRF-Token` on every unsafe API request, including registration and login. `POST /api/register` takes `email`, `name`, `password` (12–200 characters). Follow the emailed verification link, then sign in through `/login` or `POST /api/login` with `email` and `password`. The web login and logout forms also use CSRF protection.

| Operation | Endpoint |
| --- | --- |
| Community directory | `/` and `GET /api/communities` |
| Create community | `POST /api/communities`: `slug`, `name`, `description` |
| Join | `POST /api/communities/{id}/join` |
| Create board | `POST /api/communities/{id}/boards`: `slug`, `name`, `description`, `visibility`, `requiresReview` |
| Read board | `/boards/{id}` or `GET /api/boards/{id}/topics` with `before` and optional `q` |
| Publish | `/manage/boards/{id}/topics/new` or `POST /api/boards/{id}/topics`: `title`, `body`, `requestKey` |
| Read thread | `/topics/{id}`, `GET /api/topics/{id}`, `GET /api/topics/{id}/posts?after=...` |
| Reply | `POST /api/topics/{id}/replies`: `body` |
| Edit | `PUT /api/posts/{id}`: `body`, `reason`, `version` |
| Moderate topic | `POST /api/topics/{id}/transition`: `action`, `version` |
| Subscribe | `PUT` or `DELETE /api/topics/{id}/subscription` |
| React | `PUT /api/posts/{id}/reaction`: `kind` (`thanks`, `helpful`, `agree`) |

Boards support public, member and staff visibility. The workflow accepts approval, lock, unlock, hide, restore and archive transitions. A guard blocks republishing into an archived board. Doctrine's optimistic version checks prevent a stale editor from replacing newer content; prior bodies are kept as revisions. A publication request key can be retried for one day, with a conflict if the payload changes.

Reports use `POST /api/posts/{id}/reports` with `category`, `reason`; moderators read `/api/communities/{id}/reports` and resolve with `PUT /api/reports/{id}` (`decision`, `reason`). A report's author and reporter cannot resolve it. Community restrictions use `POST /api/communities/{id}/restrictions` with `userId`, `reason`, `hours`.

`PUT /api/account/digest` takes an `enabled` boolean. `/api/notifications` lists the inbox; `PUT /api/notifications/{id}` marks one read. Owners transfer a community with `POST /api/communities/{id}/transfer` and `user_id`. `/api/account/export` returns account-owned data. `DELETE /api/account` requires the current password and first requires ownership transfer.

## Delivery and verification

Publication and queued `TopicActivity` messages use the same database transaction when the Doctrine transport is configured. Notification handling uses a per-user event key, checks current visibility and tolerates replay. Digests check current subscriptions and visibility again, are limited to 100 activity items, and preserve Message-ID across retries. SMTP delivery remains at least once if a process fails after external acceptance but before local acknowledgment.

```sh
composer test
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
```

Tests create the schema in memory and exercise services, Workflow, Messenger handlers, ownership transfer, privacy, and actual Kernel HTTP requests using an in-memory session. They do not create a physical database fixture or send mail. The integration transport is in memory; production transport provisioning and worker supervision are explicit deployment steps.

Moderators archive or reopen a board through `PUT /api/boards/{id}/archive` with an `archived` boolean. SQLite connections enable foreign keys, WAL and a five-second busy timeout through a Doctrine middleware.
