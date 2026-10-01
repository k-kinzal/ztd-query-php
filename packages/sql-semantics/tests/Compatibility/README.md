# Declaration compatibility corpus

`Tests\Contract\DeclarationCorpus` contains the supplied declaration regressions and related boundary cases. `SchemaReaderTest` replays them against every shipped grammar release in the normal unit suite. Its expectations cover acceptance, column nullability, automatic numbering, effective decimal size, and unique constraints.

Run the same cases against a disposable real server from `packages/sql-semantics`:

```sh
php bin/compare-declarations.php --dialect=sqlite
SEMANTICS_DSN='mysql:host=127.0.0.1;port=3306' SEMANTICS_USERNAME=root SEMANTICS_PASSWORD=secret \
  php bin/compare-declarations.php --dialect=mysql --grammar=mysql-8.4.7
SEMANTICS_DSN='pgsql:host=127.0.0.1;port=5432;dbname=postgres' SEMANTICS_USERNAME=postgres SEMANTICS_PASSWORD=secret \
  php bin/compare-declarations.php --dialect=pg --grammar=pg-17.2
```

The runner creates a randomly named database (MySQL) or schema (PostgreSQL), drops it in `finally`, and uses an in-memory SQLite database. It compares the corpus, semantic model, and server catalog. A nonzero exit reports any mismatch. It does not use an application's existing tables.

The catalog comparison reads MySQL unique constraints directly, because [`COLUMN_KEY` may report a nonnullable unique key as `PRI`](https://dev.mysql.com/doc/refman/8.0/en/information-schema-columns-table.html). It sign-extends PostgreSQL's eleven-bit numeric scale using the server's [`numeric_typmod_scale` rule](https://github.com/postgres/postgres/blob/REL_17_STABLE/src/backend/utils/adt/numeric.c), including negative scales.

## The sql-fixture investigation inputs

`Tests\Contract\FixtureCorpus` preserves the 381 distinct inputs from the integration investigation: 135 tagged MySQL, 135 tagged PostgreSQL, and 111 tagged SQLite. They were extracted from `packages/sql-fixture/tests` at `a6ea1e64fff2ac560376c08daacb7c4f9aea3613`. Each key records its source test path. The original comparison selected a dialect from that path (defaulting shared tests to MySQL); these tags and the SQL are retained, including negative tests and scripts.

`AnalyzerTest` runs every input in the normal unit suite. It verifies table names and column order, reconstructed SQL stability, and independence from parser nodes. Twenty inputs have invalid syntax in their assigned dialect. Nine more are rejected for conflicting NULL constraints, multiple primary keys, an unindexed AUTO_INCREMENT column, or expressions in SQLite table keys. The remaining 352 inputs retain a complete typed statement. Seven of those use query-derived columns or `LIKE`: their typed commands remain available, but a complete table definition cannot be inferred without the source relation. No fabricated column list is asserted.

The original sql-fixture output is not the semantic oracle. PostgreSQL folds unquoted names; SQLite ordinary primary keys can remain nullable; fractional-second precision is not a string length; declared numeric arguments and effective numeric size are distinct. The focused server corpus above checks the feedback's behavioral corrections directly against the database, while the full input corpus guards statement structure and declaration extraction.
