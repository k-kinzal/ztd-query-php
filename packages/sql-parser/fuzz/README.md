# Fuzzing the parsers with sql-faker

`composer fuzz:mysql`, `composer fuzz:pg` and `composer fuzz:sqlite` run one PHP-Fuzzer
target each. Every target does three things: compile the fuzzer input into a sql-faker
generation plan, generate one statement from that plan, and parse the statement with the
parser of the same grammar release.

The statement and the parse table come from the same upstream grammar, so any statement
sql-faker derives is in the language the table accepts. A rejection is therefore a
finding: the target throws, PHP-Fuzzer saves the input as `crash-<hash>.txt`, and the
message carries the SQL, the parser error and the input in hex.

`MYSQL_VERSION` selects the MySQL release (default `8.4.7`); PostgreSQL is pinned to 17.2
and SQLite to 3.47.2, the releases sql-faker ships grammars for. sql-faker's own fuzzing
validates the statements it generates against a live server for the MySQL 8.4.7,
PostgreSQL 17.2 and SQLite 3.47.2 releases; for the older MySQL releases it may still
generate statements the server rejects, which this parser rejects too.

A finding is replayed with the same target:

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_mysql_parse.php crash-<hash>.txt
```

The generator reads bytes only when compiling a plan, so repeating an input produces the
same SQL, which is what lets PHP-Fuzzer shrink and replay a finding. The corpus and
coverage directories are ignored by git.
