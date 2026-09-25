<!-- NOTE: You do not have permission to overwrite this file. Please ask a human operator to perform the changes for you. -->
# AGENTS

## This Project

This project builds a complete Zero Table Dependency (ZTD) implementation for PHP.

ZTD runs tests on a real database engine without ever touching a physical table. Before a query reaches the database, every table it references is replaced by a CTE holding fixture rows (CTE shadowing), and every INSERT, UPDATE and DELETE is turned into a SELECT that returns the rows the write would produce (result select query). Those rows are kept in the session, so later queries see the writes. One empty schema serves every test: no migrations, no seeding, no cleanup, and tests run in parallel without interfering.

The hard part is "complete". A rewrite must keep the meaning of whatever SQL the application sends, in every dialect and version, and a rewrite that is slightly wrong does not fail: it silently returns wrong test results. Hand-picked test cases cannot establish that. ZTD has to understand SQL exactly as the server does, and its behavior has to be checked against the server itself.

That is why the repository holds so many packages. The `ztd-query-*` packages are the product, split only so that users install the dialect and driver they need. Everything else exists for quality, and it is the core of this project: reading the official grammars instead of approximating them, parsing and binding SQL the way the server does, generating every statement form the grammars allow, running the statements against the real servers and comparing the results, and tracing each specification back to the manual it comes from. Correctness comes from that machinery, not from examples.

The repository is also an experiment in how AI agents can build something this complex correctly, so every claim about behavior has to be backed by tests, fuzzing, or a specification.

## Supported Versions

### ZTD Query (ztd-query-*)

- PHP 8.1+
- MySQL 8.0–9.1
- PostgreSQL 16–17
- SQLite 3.x

### Other packages

- PHP 8.1+
- MySQL 5.6–9.1
- PostgreSQL 16–17
- SQLite 3.x

## Development Rules

- Work in a dedicated git worktree branched from `main`; do not work in the main checkout.
- Deliver every change through a pull request.
- This is an English project: write every artifact in English, including code, comments, commit messages, pull requests, and documentation.
- Write documentation for the users of the libraries. `AGENTS.md` is the only exception.

## Documents

- [packages/bison-parser/README.md](packages/bison-parser/README.md) - Reading GNU Bison grammar files into a lossless syntax tree, and printing it back
- [packages/container/README.md](packages/container/README.md) - Container definitions for testcontainers-php used across the repository, their image versions, and how to use them
- [packages/lemon-parser/README.md](packages/lemon-parser/README.md) - Reading Lemon grammar files into a lossless syntax tree, and printing it back
- [packages/requirements/README.md](packages/requirements/README.md) - Linking source text, EARS specifications, and tests; installation and usage
- [packages/requirements/docs/cli.md](packages/requirements/docs/cli.md) - Command line interface: commands, options, and report output
- [packages/requirements/docs/extensions.md](packages/requirements/docs/extensions.md) - Source extensions that retrieve reference material and runner extensions that run tests
- [packages/requirements/docs/format.md](packages/requirements/docs/format.md) - Configuration and definition document format (version 1), including the Markdown profile
- [packages/requirements/docs/lint.md](packages/requirements/docs/lint.md) - Validation rules applied to every document before other commands run
- [packages/requirements/docs/traceability.md](packages/requirements/docs/traceability.md) - Design of source traceability: problem, goals, and model
- [packages/requirements/examples/markdown/decisions.md](packages/requirements/examples/markdown/decisions.md) - Example definition document in the Markdown profile, without a source
- [packages/requirements/examples/markdown/grammar.md](packages/requirements/examples/markdown/grammar.md) - Example definition document in the Markdown profile, traced to an HTML source
- [packages/requirements/tests/Fixtures/source.md](packages/requirements/tests/Fixtures/source.md) - Test fixture: a minimal Markdown source
- [packages/sql-catalog/README.md](packages/sql-catalog/README.md) - Cataloging the SQL a PHP application can issue: usage, statuses, extensions, and reporters
- [packages/sql-catalog/docs/analysis.md](packages/sql-catalog/docs/analysis.md) - How the analysis reconstructs the SQL a PHP application issues
- [packages/sql-catalog/docs/extensions.md](packages/sql-catalog/docs/extensions.md) - Source models that extensions provide for framework database APIs
- [packages/sql-catalog/docs/format.md](packages/sql-catalog/docs/format.md) - The catalog file format written by the JSON reporter, and the HTML report
- [packages/sql-catalog/docs/laravel.md](packages/sql-catalog/docs/laravel.md) - Laravel support: enabling the extension and what it recognizes
- [packages/sql-catalog/docs/verification.md](packages/sql-catalog/docs/verification.md) - How the analyzer's accuracy is checked and what it measures
- [packages/sql-catalog/fuzz/README.md](packages/sql-catalog/fuzz/README.md) - Fuzz targets and how to run them
- [packages/sql-faker/README.md](packages/sql-faker/README.md) - Grammar-based SQL generation: installation, providers, and supported versions
- [packages/sql-faker/docs/algorithm.md](packages/sql-faker/docs/algorithm.md) - How SQL is derived from the official grammars, and its limitations
- [packages/sql-faker/docs/faker.md](packages/sql-faker/docs/faker.md) - The FakerPHP provider interface
- [packages/sql-faker/docs/generator.md](packages/sql-faker/docs/generator.md) - The SqlGenerator interface for generating SQL without Faker
- [packages/sql-faker/docs/plan.md](packages/sql-faker/docs/plan.md) - Generation plans that select syntax and constrain its structure
- [packages/sql-faker/fuzz/README.md](packages/sql-faker/fuzz/README.md) - Fuzz targets that run generated SQL against the databases
- [packages/sql-faker/seeds/README.md](packages/sql-faker/seeds/README.md) - Grammar coverage seed corpora for fuzz targets
- [packages/sql-fixture/README.md](packages/sql-fixture/README.md) - Generating fixture data from CREATE TABLE statements, databases, or DDL files
- [packages/sql-fixture/fuzz/README.md](packages/sql-fixture/fuzz/README.md) - Fuzz targets and how to run them
- [packages/sql-formatter/README.md](packages/sql-formatter/README.md) - Formatting SQL with layout presets: installation and usage
- [packages/sql-formatter/docs/design.md](packages/sql-formatter/docs/design.md) - Formatter design and why it builds on sql-parser
- [packages/sql-formatter/docs/verification.md](packages/sql-formatter/docs/verification.md) - How formatting output and equivalence are verified
- [packages/sql-formatter/fuzz/README.md](packages/sql-formatter/fuzz/README.md) - Format and equivalence fuzz targets per database
- [packages/sql-parser/README.md](packages/sql-parser/README.md) - Lossless LALR(1) SQL parsers built from the official grammars
- [packages/sql-parser/docs/architecture.md](packages/sql-parser/docs/architecture.md) - The layers from grammar to parse table to syntax tree
- [packages/sql-parser/docs/usage.md](packages/sql-parser/docs/usage.md) - Parsing, tokenizing, and working with the syntax tree
- [packages/sql-parser/fuzz/README.md](packages/sql-parser/fuzz/README.md) - Fuzz targets that parse sql-faker statements and write them back
- [packages/sql-semantics/README.md](packages/sql-semantics/README.md) - Binding SQL to a schema: installation and usage
- [packages/sql-semantics/docs/design.md](packages/sql-semantics/docs/design.md) - Design of the semantic phase and the information it derives
- [packages/sql-semantics/docs/support.md](packages/sql-semantics/docs/support.md) - Supported language and the confidence contract
- [packages/sql-semantics/docs/verification.md](packages/sql-semantics/docs/verification.md) - How the public API is tested
- [packages/ztd-query-core/README.md](packages/ztd-query-core/README.md) - ZTD Query overview, installation, and usage
- [packages/ztd-query-core/docs/mechanism.md](packages/ztd-query-core/docs/mechanism.md) - Overview and design of the ZTD mechanism
- [packages/ztd-query-mysql/README.md](packages/ztd-query-mysql/README.md) - MySQL platform support: installation and usage
- [packages/ztd-query-mysql/docs/spec.md](packages/ztd-query-mysql/docs/spec.md) - How ZTD handles MySQL SQL statements
- [packages/ztd-query-mysql/docs/support-matrix.md](packages/ztd-query-mysql/docs/support-matrix.md) - Supported MySQL statements and their status
- [packages/ztd-query-mysqli-adapter/README.md](packages/ztd-query-mysqli-adapter/README.md) - MySQLi adapter: installation and usage
- [packages/ztd-query-pdo-adapter/README.md](packages/ztd-query-pdo-adapter/README.md) - PDO adapter: installation and usage
- [packages/ztd-query-postgres/README.md](packages/ztd-query-postgres/README.md) - PostgreSQL platform support: installation and usage
- [packages/ztd-query-postgres/docs/spec.md](packages/ztd-query-postgres/docs/spec.md) - How ZTD handles PostgreSQL SQL statements
- [packages/ztd-query-sqlite/README.md](packages/ztd-query-sqlite/README.md) - SQLite platform support: installation and usage
- [packages/ztd-query-sqlite/docs/spec.md](packages/ztd-query-sqlite/docs/spec.md) - How ZTD handles SQLite SQL statements
