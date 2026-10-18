# Harbor Laravel

A Laravel 13 community platform with an authenticated API, community directory and password-reset pages. Each community controls memberships, boards, publication review and moderation. Application persistence uses Eloquent relationships, enum casts and soft-deleted discussion resources.

The service includes Sanctum access tokens, email verification, password reset, throttling, FormRequest validation, policy authorization, cursor pagination, tagged discussions, post revisions, optimistic version checks, private attachments, subscriptions, reactions, moderation reports, temporary restrictions, ownership transfer, notifications, personal data export and account erasure.

## Install and run

PHP 8.4.1+, PDO SQLite, mbstring, XML and the standard Laravel extensions are required.

```sh
composer install --optimize-autoloader
cp .env.example .env
php artisan key:generate
# Set DB_DATABASE to the absolute path of your database, then create that file.
touch database/database.sqlite
php artisan migrate --force
php artisan serve --host=127.0.0.1 --port=8001
```

Use SQLite for the committed service configuration and verification. In production, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the HTTPS origin, a unique `APP_KEY`, secure session cookies and the intended SMTP settings. Serve `public/` through PHP-FPM, give the process access to `storage/` and `bootstrap/cache/`, and keep private attachments outside the public filesystem. Never use the test suite's deterministic application key in deployment.

```sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:work --tries=5 --timeout=120
php artisan schedule:work
```

Use the database queue connection (or a configured production queue) and supervise workers. `harbor:dispatch-outbox` runs every minute, `harbor:escalate-reports` every 15 minutes, and retention and expired-token pruning run daily. The committed default broadcasting connection is `log`; topic change events are emitted on authorized private channels, and transport configuration can be supplied by a deployment.

## Client workflow

Register at `POST /api/register` with `name`, `email`, `password`, `password_confirmation`. Passwords require at least 12 characters, mixed case and a number. The response contains a 30-day Sanctum bearer token. Email verification is required for publishing and community management. Configure mail delivery before inviting users. `/api/email/resend` resends a verification link; `/api/forgot-password` and `/api/reset-password` use Laravel's password broker. `/api/login` issues a token and `/api/logout` revokes the current token.

Use `Authorization: Bearer TOKEN` for authenticated requests. Public read routes also recognize a bearer token when applying private-board visibility.

| Operation | Endpoint and input |
| --- | --- |
| Create community | `POST /api/communities`: `slug`, `name`, `description` |
| Join | `POST /api/communities/{id}/join` |
| Create board | `POST /api/communities/{id}/boards`: `slug`, `name`, `description`, `visibility`, `requires_review` |
| Publish | `POST /api/boards/{id}/topics`: `title`, `body`, optional `tags`; `Idempotency-Key` header |
| Search | `GET /api/boards/{id}/topics?q=...&tag=...` |
| Read | `GET /api/topics/{id}` and `/api/topics/{id}/posts` |
| Reply | `POST /api/topics/{id}/replies`: `body` |
| Edit | `PUT /api/posts/{id}`: `body`, `reason`, `version` |
| Revision history | `GET /api/posts/{id}/revisions` |
| Upload | `POST /api/posts/{id}/attachments`: multipart `attachment` |
| Download | `GET /api/attachments/{id}`; visibility is checked at download time |
| Subscribe | `PUT` or `DELETE /api/topics/{id}/subscription` |
| React | `PUT /api/posts/{id}/reaction`: `kind` (`helpful`, `thanks`, `agree`) |

Boards are `public`, `members` or `staff`. Ordinary members can edit their visible posts for 30 minutes while the topic remains published; moderators can edit with a recorded reason. Attachments are private, limited to five per post, 10 MiB each, and PDF, text, PNG or JPEG. API resources omit storage paths, account emails and passwords.

Moderators apply `POST /api/topics/{id}/transition` with `action` and `version`; actions are `approve`, `lock`, `unlock`, `hide`, `restore`, `archive`. Reports are created at `POST /api/posts/{id}/reports` with `category` and `reason`, listed at `/api/communities/{id}/reports`, and resolved at `PUT /api/reports/{id}` with `decision` and `reason`. Reporters and authors cannot resolve their own case. `POST /api/communities/{id}/restrictions` takes `user_id`, `reason`, `hours`; `DELETE /api/restrictions/{id}` revokes one.

Owners manage roles at `PUT /api/communities/{id}/members/{user}` and transfer ownership at `POST /api/communities/{id}/transfer`. Community audit records are available to moderators at `/api/communities/{id}/audit`. `/api/account/export` returns account-owned data; `DELETE /api/account` requires the current password and ownership transfer first. Inbox notifications are available at `/api/notifications` and can be marked read with `PUT /api/notifications/{id}`.

## Delivery and retention

Publishing writes the topic, initial post, subscription, request receipt, audit record and outbox event transactionally. Jobs are dispatched after commit. The scheduled outbox dispatcher recovers events left behind by a process failure. The delivery job checks current visibility and commits database notifications and event acknowledgment together; replaying it does not duplicate inbox entries. Broadcasts carry a topic ID and version, and clients refetch authorized content.

```sh
php artisan harbor:dispatch-outbox
php artisan harbor:escalate-reports
php artisan harbor:prune --dry-run
composer test
```

Privacy reports are escalated after four hours, harassment after 12, copyright after 24 and other categories after 48. Read notifications are kept for 90 days, delivered events for 30 days, idempotency receipts for one day and audit entries for one year. Tests exercise database transactions and HTTP endpoints against in-memory SQLite; external queues and mail servers are not contacted.

Moderators archive or reopen a board with `PUT /api/boards/{id}/archive` and an `archived` boolean. Erasure marks owned attachments for deletion transactionally; a queued job removes the private files, and the five-minute scheduler retries any remaining deletion markers.
