# Fuzzing the formatter

Two kinds of PHP-Fuzzer targets, one of each per database, in the shape of the sql-faker
targets: an entry point per database and a `Target` class it hands every input to.

| Target | Input | Finding |
|--------|-------|---------|
| `fuzz_mysql_format.php`, `fuzz_pg_format.php`, `fuzz_sqlite_format.php` | A selector byte, then bytes read as SQL text | Formatting fails its own verification, throws anything but the parser's rejection, dies, times out, or is not idempotent |
| `fuzz_mysql_equivalence.php`, `fuzz_pg_equivalence.php`, `fuzz_sqlite_equivalence.php` | A sql-faker generation plan | The database answers the formatted statement differently from the original, or formatting fails its own verification |

`composer fuzz` runs the smoke set: the three format targets and the SQLite equivalence
target, 100 mutations each. A finding is saved as `crash-<hash>.txt` in the package
directory and replayed with the target that found it:

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_pg_format.php crash-<hash>.txt
```

## Format targets

```sh
composer fuzz:format:mysql
composer fuzz:format:pg
composer fuzz:format:sqlite
```

The first input byte selects the layout: its two low bits pick the preset and the next
four bits the indentation width, so every mutation of that byte reaches another of the
64 layouts. The remaining bytes are the SQL text. Text the parser rejects is not a
finding: the parser's lexical and syntax exceptions are the documented answer to invalid
input. Every other exception is one, and so is a `FormattingException`, because the
formatter has then produced text whose syntax tree differs from the input's. Output that
formats to something else the second time is a finding as well. Inputs are limited to a
selector byte and 4 KiB of text. `MYSQL_VERSION` selects the MySQL grammar (default `8.4.7`).

## Equivalence targets

```sh
composer fuzz:equivalence:mysql            # SQLFORMATTER_STYLE selects the layout, default expanded
composer fuzz:equivalence:pg:river         # one script per database and preset
composer fuzz:equivalence:sqlite:compact
```

The input is compiled into a sql-faker generation plan exactly as sql-faker's own syntax
targets compile it, from the statement rule of the database's default grammar release,
so the [seed corpora](../../sql-faker/seeds/) replay here as they are. The statement is
formatted with the preset the script name or `SQLFORMATTER_STYLE` selects, and the
original and the formatted text are executed one after the other. The two answers must
be the same: the same rows in the same order, the same affected row count or command tag,
or the same error code and message. Result column names are not compared, because MySQL
and SQLite name an unaliased expression column after its source text, which the layout
changes by design. A statement whose answer differs is run once more; if the original
does not answer the same way twice, as with `CURRENT_TIMESTAMP` or a statement that
changed server state, nothing is reported. A statement the parser rejects is skipped:
whether every generated statement parses is what the sql-parser fuzz targets check.

- **MySQL** starts a Testcontainer (`MYSQL_VERSION` selects the release, default `8.4.7`)
  that other test runs may share. Statements run as a user of their own with rights on a
  database of their own only, so the server denies everything that would reach beyond it,
  such as `SHUTDOWN`, `SET GLOBAL` or `ALTER USER` on other accounts, which sql-faker does
  generate. The database is dropped and created again before every statement, so both
  texts meet the same empty schema. A statement that ends the connection or changes the
  fuzz user's own password is followed by a fresh user and connection, and counts as the
  same answer both times. A parse error quotes the remaining text and names its line,
  which is the statement's own layout, so both are removed before the message is compared.
- **PostgreSQL** starts the 17.2 Testcontainer. Every statement runs inside a transaction
  that is rolled back; statements PostgreSQL refuses in a transaction block fail the same
  way both times. Errors are compared by SQLSTATE and primary message, which carry no
  position. A `COPY` leaves the session busy and is followed by a fresh connection.
- **SQLite** runs every statement on its own in-memory database through PDO, with the
  process temporarily inside a scratch directory so that file names in `ATTACH` or
  `VACUUM INTO` resolve there. Any linked SQLite release serves, since both texts meet the
  same one.

The provider records grammar coverage under `fuzz/coverage/<database>/`; set
`SQLFAKER_COVERAGE=0` to run without it. A lost database connection ends the run with exit
status 2 instead of a finding. The scripts pass `--timeout=60` so that the provider's
one-time grammar analysis fits into the first input, and start PHP-Fuzzer with
`php -d memory_limit=-1` because the seed corpora exceed PHP's default limit.

Replaying the seeds without mutating them checks every statement form once:

```sh
mkdir -p fuzz/corpus/equivalence-sqlite
cp ../sql-faker/seeds/sqlite/sqlite-3.47.2/*.txt fuzz/corpus/equivalence-sqlite/
composer fuzz:equivalence:sqlite:river -- --max-runs=0
```

The corpus and coverage directories are ignored by git.

## Continuous integration

`.github/workflows/sql-formatter-fuzz.yml` runs nightly and on demand: one job per format
target and one per database and preset for the equivalence targets, 15 jobs in all. An
equivalence job copies the sql-faker seeds into its corpus first. Each job restores the
corpus that earlier runs evolved, fuzzes for the configured number of mutations, opens an
issue for a new finding, and saves the corpus and the input that caused the finding as
artifacts.
