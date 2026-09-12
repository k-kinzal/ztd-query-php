# Fuzzing SQLFaker against the databases

`composer fuzz:mysql`, `composer fuzz:pg` and `composer fuzz:sqlite` run one PHP-Fuzzer
target each. Every target does four things: start the database, compile the fuzzer
input into a generation plan, generate one statement from that plan, and hand the
statement to the database.

MySQL and PostgreSQL start as disposable Testcontainers. `MYSQL_VERSION` selects the
MySQL release (default `8.4.7`); the matching grammar version is chosen with it.
PostgreSQL is pinned to 17.2. SQLite uses PHP's PDO SQLite extension, which must link
3.47.2, the release the grammar was built from.

## What the database check does

The statement is prepared, never executed: MySQL through the server's `PREPARE`,
PostgreSQL through an extended-protocol `Parse`, SQLite through `prepare`. A fuzz run
has no schema, so each check lists the errors the grammar cannot avoid, such as unknown
table names or column-count mismatches, with the reason each one is tolerated. Every
other rejection is a finding: the check throws, PHP-Fuzzer saves the input as
`crash-<hash>.txt`, and the message carries the SQL, the error and the input in hex.
A lost database connection ends the run with exit status 2 instead of a finding.

A finding is replayed with the same target:

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_mysql_syntax.php crash-<hash>.txt
```

## Plans and determinism

The generator reads bytes only when compiling a plan. The first four bytes select an
expansion budget; the remaining bytes supply production and lexical decisions, so the
targets allow inputs of up to 80,004 bytes. Repeating a plan produces identical SQL
independently of Faker state, which is what lets PHP-Fuzzer shrink and replay a finding.

## Grammar coverage

The provider records which productions each generation reached and which of them
survived into the output. The targets pass a `GrammarCoverage` bound to
`fuzz/coverage/<database>/`, and the recorder persists its cumulative snapshot itself,
every hundred generations and at shutdown. Set `SQLFAKER_COVERAGE=0` to run without
recording. The corpus and coverage directories are ignored by git.
