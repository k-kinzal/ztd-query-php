# Database packages

SQL Semantics is developed as four Composer packages in this monorepo. The common
`k-kinzal/sql-semantics` runtime contains `Core`, `Facade\Semantics`, and the
parser-independent `Statement` layer. Each `sql-semantics-{mysql,postgres,sqlite}`
package owns one platform implementation, its `Dialect` enum, generated models,
version-specific construction maps, tests, and fuzz target. Existing model class
names are unchanged by packaging.

Runtime dependencies point from a database package to the common runtime. The
common runtime has no dependency on a concrete SQL Semantics database package.
Its test suite installs all three as development dependencies to exercise the
shared binding contracts against real dialects. A database package's test suite
installs only its own SQL Semantics implementation.

## Regenerating models

Run from a database package after `composer install`:

```sh
composer build:models
composer build:models:check
```

The common runtime exports `bin/build-models.php` through Composer's `bin` field.
For example, the MySQL script runs:

```sh
php vendor/bin/build-models.php --dialect=mysql --output=resources
```

The accepted dialects are `mysql`, `postgresql`, and `sqlite`. Every supported
release of the selected database is compiled together, sharing identical model
forms across releases. Output paths are relative to the calling package.
Downloads are cached in its `build/sources`, and staging uses its
`build/model-resources/<dialect>`. `--check` detects additions, changes, and
removals without modifying committed resources. Generated output is committed;
installing the library never runs the generator.

MySQL and PostgreSQL declare `k-kinzal/bison-parser` as a development dependency;
SQLite declares `k-kinzal/lemon-parser`. Each package declares its own build
dependencies because Composer does not install dependencies' `require-dev` lists.
The command uses the calling Composer project's autoloader and sql-parser's
resource registry, so the same scripts work from a split repository.

## Fuzzing

Each database package provides `composer fuzz:seeds`, `composer fuzz:smoke`, and
`composer fuzz:roundtrip -- --max-runs=10000`. These copy sql-faker's seed byte
plans unchanged, generate SQL with the matching dialect/version, analyze it, and
compare Compact formatting of the original SQL and `Statement::toString()`.
Every exception and mismatch is a finding; no statement family is skipped.
MySQL accepts `MYSQL_VERSION`, defaulting to `8.4.7`. PostgreSQL uses `pg-17.2`
and SQLite uses `sqlite-3.47.2`.

## Publishing

The existing split workflow exports each package directory directly to its
matching `k-kinzal/sql-semantics*` repository. Files are not rearranged during
publication. Monorepo path repositories and development lock files are removed
by the existing workflow, and monorepo dependency constraints follow the release
tag. Register each split repository with Packagist before publishing releases.

Generated construction maps depend on the matching sql-parser grammar resources.
Release parser, common runtime, and database packages with compatible versions.
The current parser dependency itself still ships all three databases.
