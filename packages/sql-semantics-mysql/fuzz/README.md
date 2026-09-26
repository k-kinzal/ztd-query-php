# MySQL statement round-trip fuzzing

```sh
composer fuzz:seeds
composer fuzz:smoke
composer fuzz:roundtrip -- --max-runs=10000
```

Run from the package directory. Seeds come directly from sql-faker, without
translation or filtering. Every generated SQL statement must analyze and print;
Compact formatting of the original and reconstructed SQL must be identical.
Exceptions and differences fail the run, including failures during corpus replay.
The runner propagates PHP-Fuzzer findings even when its process exits with zero.

The entry point is `fuzz_mysql_roundtrip.php`.
Set MYSQL_VERSION to select a shipped MySQL release; the default is 8.4.7.
Set `SQLFAKER_COVERAGE=0` to disable grammar coverage recording.
