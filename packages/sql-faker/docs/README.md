# SQL Faker documentation

SQL Faker generates SQL strings for MySQL, PostgreSQL, and SQLite. Use it to
exercise a SQL parser, explore statement forms, or supply varied input to tools
that inspect SQL. No database connection is needed to generate strings.

- [User guide](usage.md) — installation, choosing a dialect, statement selection,
  depth, reproducibility, and output behavior.
- [API reference](api.md) — provider methods, dialect differences, argument bounds,
  and exceptions you can handle.
- [Supported versions](versions.md) — version tags and defaults for each dialect.

- [Native verification](../fuzz/README.md) — deterministic byte plans, parser verdicts, persistent coverage and replay.
- [Design review resolution](design-review-resolution.md) — implemented corrections and the remaining scope of each guarantee.

Start with the [first example](usage.md#generate-your-first-statement). Generated
SQL follows the selected dialect's grammar; executing it also requires a suitable
database schema and server configuration.
