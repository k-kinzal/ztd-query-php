# Harbor Native Web

A community publishing HTTP service built with native PHP, PDO and SQLite. It serves a JSON API for authenticated clients and HTML pages for public community and discussion reading. It has no application framework or shared dependency on the other targets.

Communities have an owner, moderators and members. Boards can be public, members-only or staff-only, and can require approval. Discussions support replies, revision history, optimistic edit checks, approval, locking, hiding and archival. Members can follow discussions, bookmark them and react to posts. Reports require an independent moderator; timed community restrictions prevent posting. Notifications are delivered through a transactional outbox and an idempotent inbox worker. Accounts support export and erasure, with ownership transfer required before an owner leaves.

## Run

PHP 8.3+, PDO SQLite and mbstring are required. The committed lock also records the test dependency versions.

```sh
composer install --optimize-autoloader
php bin/harbor migrate
HARBOR_INSECURE_HTTP=1 php -S 127.0.0.1:8000 -t public public/router.php
```

`HARBOR_DATABASE` overrides the database path, which defaults to `var/harbor.sqlite`. The runtime directory must be writable by the service user. SQLite foreign keys, WAL mode and a busy timeout are enabled. Writes use `BEGIN IMMEDIATE` so permission checks and state changes share the same write reservation. Schema changes are tracked in `migrations`.

For deployment, serve `public/` through a TLS-terminating web server and PHP-FPM, forwarding unknown paths to `index.php`. Keep `HARBOR_INSECURE_HTTP` unset: session cookies are secure and HttpOnly by default. Keep the database and backups outside the document root. This deployment uses one local SQLite database; it does not assume a distributed write topology.

## Client workflow

1. `GET /api/session` obtains a session cookie and `csrf` token. Send that cookie and `X-CSRF-Token` on every mutating request, including registration and login.
2. `POST /api/register` accepts `name`, `email` and a password of at least 12 characters. Registration signs in and rotates the token. `POST /api/login` accepts `email` and `password`; `/api/logout` revokes the current session.
3. `POST /api/communities` accepts `slug`, `name`, `description`. The creator becomes the owner. Members join through `/api/communities/{community}/join`.
4. `POST /api/communities/{community}/boards` accepts `slug`, `title`, `visibility` (`public`, `members`, `staff`) and `requires_review`.
5. `POST /api/boards/{board}/topics` accepts `title`, `body` and an `Idempotency-Key` header of 8–128 characters. Reusing a key with different content is a conflict. Keys are retained for 24 hours.
6. `POST /api/topics/{topic}/replies` accepts `body`. `PUT /api/posts/{post}` accepts `body`, `reason` and the observed `version`; stale writes receive 409.

Read `/api/communities`, `/api/communities/{id}/boards`, `/api/boards/{id}/topics`, `/api/topics/{id}`, and `/api/boards/{id}/search?q=...`. Topic lists accept `before`; posts accept `after`. Pages contain at most 50 topics or 100 posts.

Moderators use `POST /api/topics/{id}/transition` with `action` and `version`; actions are `approve`, `lock`, `unlock`, `hide`, `restore`, `archive`. Reports are filed at `POST /api/posts/{id}/reports` with `reason`, listed at `/api/communities/{id}/reports`, and resolved at `PUT /api/reports/{id}` with `decision` (`dismissed` or `upheld`) and `reason`. Restrictions use `/api/communities/{id}/sanctions` with `user`, `reason`, `hours` (1–2160).

`PUT`/`DELETE /api/topics/{id}/subscription` and `/bookmark` manage engagement. `PUT /api/posts/{id}/reaction` accepts `reaction` (`helpful`, `thanks`, `agree`). `/api/notifications` lists the inbox; `PUT /api/notifications/{id}` marks a notification read. Only the recipient can update it.

`PUT /api/communities/{id}/members/{member}` changes `role` to `member` or `moderator`. `POST /api/communities/{id}/transfer` accepts the successor's `user` ID. `GET /api/account/export` returns account-owned data. `DELETE /api/account` requires the current `password`; it removes authored content and membership data while preserving referential integrity. `PUT /api/password` accepts `current` and `password`.

## Operations and verification

```sh
php bin/harbor notifications:deliver
php bin/harbor privacy:purge
php bin/harbor database:check
composer test
```

Run delivery every minute and retention daily. Delivery commits the inbox writes and outbox acknowledgment together, checks current visibility, and skips the actor. Retention removes read notifications after 90 days, delivered events after 30 days, request receipts after one day and audit records after one year. Back up the SQLite database using SQLite's backup tooling rather than copying a live WAL file alone.

Tests exercise HTTP workflows using an in-memory database and an in-memory session: publishing, retries, approval, edit conflicts, board permissions, independent moderation, posting restrictions, ownership transfer and erasure. Production web-server configuration and backup restoration are deployment checks, not measured by these tests.

Moderators archive or reopen a board through `PUT /api/boards/{id}/archive` with an `archived` boolean. Archived boards remain readable but refuse new topics, replies and edits.
