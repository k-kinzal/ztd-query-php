# Harbor Operations

A standalone Symfony Console service for community ingestion and moderation operations. It owns its SQLite database and does not connect to the other benchmark targets.

The service imports external community posts, applies configurable moderation rules, manages assigned review cases and independent appeals, sends opt-in daily digests, and processes two-operator erasure requests. Imports, moderation state changes, retention holds and background work use persistent state and audit records.

## Install

PHP 8.4.1+, PDO SQLite, mbstring and pcntl are required for the worker's signal support.

```sh
composer install --optimize-autoloader
export HARBOR_OPERATOR=alice
php bin/harbor database:migrate
php bin/harbor community:create engineering 'Engineering' --retention=365
php bin/harbor source:add 1 support-api
php bin/harbor list
```

`HARBOR_DATABASE` defaults to `var/operations.sqlite`. SQLite foreign keys and WAL are enabled. Operators run under trusted OS accounts; `HARBOR_OPERATOR` is the attributable principal attached to audit records, not an authentication mechanism. In deployment, set it through each operator's controlled launcher rather than accepting it from untrusted clients.

## Ingestion and exports

`posts:import SOURCE_ID` reads JSON Lines from standard input. Each record contains:

```json
{"id":"external-post-17","memberId":"external-user-8","email":"member@example.org","displayName":"Community Member","title":"A deployment question","body":"How can we safely roll out a schema change?","publishedAt":"2026-10-17T10:00:00+00:00"}
```

The source identifies the community. Symfony Serializer constructs typed records and Validator checks them. A transaction covers the entire stream: malformed input rolls back earlier rows and audit entries. Duplicate identical records are accepted; changed content under an existing external ID is rejected. An erased member cannot be recreated by a later import. Publication dates must not be in the future, and individual records are limited to 256 KiB.

```sh
php bin/harbor posts:import 1 < export.jsonl
php bin/harbor posts:export 1 --state=published --format=jsonl > published.jsonl
php bin/harbor posts:export 1 --state=published --format=csv > published.csv
```

Exports use keyset pagination and stream output. CSV cells are protected against spreadsheet formula interpretation. Import content is immutable: corrections require a newly identified source revision or a moderation action; there is no implicit overwrite command.

## Moderation and appeals

```sh
php bin/harbor rule:add 1 'Credential exposure' 'access token' --severity=urgent
php bin/harbor moderation:scan --limit=1000
php bin/harbor case:list
php bin/harbor case:claim 1
php bin/harbor case:resolve 1 uphold 'The message exposes another persons credential.'
php bin/harbor appeal:open 1 'The value is a documented public sample token.'
HARBOR_OPERATOR=bob php bin/harbor appeal:decide 1 restore 'The published documentation confirms this is a sample.'
```

Rules match literal phrases case-insensitively; they do not claim to classify arbitrary abuse. Urgent matches quarantine content. Review matches leave it visible until a decision. A post remains removed while any upheld case exists and remains quarantined while any urgent case is open. Appeals must be decided by someone other than the original reviewer. New rules invalidate scan markers so existing content is reviewed.

## Digests and jobs

```sh
php bin/harbor digest:subscribe 1
php bin/harbor digest:schedule 2026-10-17
MAILER_DSN='smtp://mail.internal:25' HARBOR_MAIL_FROM='community@example.org' php bin/harbor queue:work --limit=100
php bin/harbor digest:unsubscribe 1
php bin/harbor queue:retry 17
```

Schedule a completed UTC day. The default `null://null` mail transport supports local execution without external delivery; configure SMTP for delivery. Scheduling is idempotent per member and day. A PDO-backed Symfony Lock serializes digest workers. Each job also has an expiring lease token, bounded retries and a dead state. Expired leases can be reclaimed; a stale worker cannot acknowledge another worker's lease. SIGINT and SIGTERM stop between jobs.

SMTP delivery is at least once: a crash after the mail server accepts a message but before acknowledgment may produce a retry. A stable Message-ID is preserved. Current opt-out and erasure state are checked again at delivery. Digests contain up to 100 posts from that UTC day; they are summaries, not complete archive exports.

## Privacy, retention and checks

```sh
php bin/harbor privacy:request 1 'Verified account owner requested permanent deletion.'
HARBOR_OPERATOR=bob php bin/harbor privacy:approve 1
php bin/harbor privacy:erase 1
php bin/harbor retention:purge --dry-run
php bin/harbor retention:purge
php bin/harbor operations:status
composer test
```

A different operator must approve erasure. Erasure removes profile contact data, disables digest delivery and removes authored content while retaining identifiers needed to prevent reimport. Retention uses each community's policy and preserves content under an open case or appeal. Run scans and workers regularly, schedule digests daily, and monitor dead jobs through `operations:status`.

Tests use CommandTester, in-memory SQLite, in-memory input streams, MockClock and Symfony's null mail transport. They cover import rollback, idempotency, moderation and appeals, stale leases, delivery, two-operator erasure and retention holds. No SMTP messages are sent by the tests.
