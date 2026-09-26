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
- [packages/requirements/docs/cli.md](packages/requirements/docs/cli.md) - Commands, options, exit codes, coverage and test results, and CI gates
- [packages/requirements/docs/configuration.md](packages/requirements/docs/configuration.md) - The configuration file (version 1): definition files, bootstrap, extensions, runners and coverage gates
- [packages/requirements/docs/definitions.md](packages/requirements/docs/definitions.md) - Definition documents (version 1): sources, items, selectors and the experimental Markdown profile
- [packages/requirements/docs/extensions.md](packages/requirements/docs/extensions.md) - Writing and registering source and runner extensions
- [packages/requirements/docs/lint.md](packages/requirements/docs/lint.md) - What lint checks, and the EARS patterns specifications must follow
- [packages/requirements/docs/traceability.md](packages/requirements/docs/traceability.md) - What requirements traces: the model, the workflow, and what it does not prove
- [packages/sql-catalog/README.md](packages/sql-catalog/README.md) - Cataloging the SQL a PHP application can issue: requirements and getting started
- [packages/sql-catalog/docs/analysis.md](packages/sql-catalog/docs/analysis.md) - What the analysis reports: statements, resolution, origins, findings and limits
- [packages/sql-catalog/docs/api.md](packages/sql-catalog/docs/api.md) - The PHP API: Analyzer, options, the catalog model and reporters
- [packages/sql-catalog/docs/cli.md](packages/sql-catalog/docs/cli.md) - Command line options, filters, exit codes and CI usage
- [packages/sql-catalog/docs/configuration.md](packages/sql-catalog/docs/configuration.md) - The .catalog.yaml configuration file and function models
- [packages/sql-catalog/docs/extensions.md](packages/sql-catalog/docs/extensions.md) - Built-in extensions, writing extensions, and source models
- [packages/sql-catalog/docs/format.md](packages/sql-catalog/docs/format.md) - The text, JSON and HTML reports
- [packages/sql-catalog/docs/extensions/doctrine.md](packages/sql-catalog/docs/extensions/doctrine.md) - Doctrine DBAL support: recognised calls and limits
- [packages/sql-catalog/docs/extensions/laravel.md](packages/sql-catalog/docs/extensions/laravel.md) - Laravel support: the dialect and the supported operations
- [packages/sql-catalog/docs/extensions/mysqli.md](packages/sql-catalog/docs/extensions/mysqli.md) - mysqli support: recognised calls and limits
- [packages/sql-catalog/docs/extensions/pdo.md](packages/sql-catalog/docs/extensions/pdo.md) - PDO support: recognised calls, bindings and limits
- [packages/sql-catalog/docs/extensions/wordpress.md](packages/sql-catalog/docs/extensions/wordpress.md) - WordPress support: wpdb calls, wpdb::prepare(), the $wpdb global and limits
- [packages/sql-faker/README.md](packages/sql-faker/README.md) - Grammar-based SQL generation: installation, providers, and supported versions
- [packages/sql-faker/docs/algorithm.md](packages/sql-faker/docs/algorithm.md) - How SQL is derived from the official grammars, and its limitations
- [packages/sql-fixture/README.md](packages/sql-fixture/README.md) - Generating fixture data from CREATE TABLE statements, databases, or DDL files
- [packages/sql-formatter/README.md](packages/sql-formatter/README.md) - Formatting SQL with layout presets: installation and usage
- [packages/sql-parser/README.md](packages/sql-parser/README.md) - Lossless LALR(1) SQL parsers built from the official grammars
- [packages/sql-semantics/README.md](packages/sql-semantics/README.md) - Binding SQL to a schema: installation and usage
- [packages/ztd-query-core/README.md](packages/ztd-query-core/README.md) - ZTD Query overview, installation, and usage
- [packages/ztd-query-core/docs/mechanism.md](packages/ztd-query-core/docs/mechanism.md) - The Zero Table Dependency model: what it is, how it works, and its scope
- [packages/ztd-query-mysql/README.md](packages/ztd-query-mysql/README.md) - MySQL platform support: installation and usage
- [packages/ztd-query-mysql/docs/spec.md](packages/ztd-query-mysql/docs/spec.md) - How ZTD handles MySQL SQL statements
- [packages/ztd-query-mysqli-adapter/README.md](packages/ztd-query-mysqli-adapter/README.md) - MySQLi adapter: installation and usage
- [packages/ztd-query-pdo-adapter/README.md](packages/ztd-query-pdo-adapter/README.md) - PDO adapter: installation and usage
- [packages/ztd-query-postgres/README.md](packages/ztd-query-postgres/README.md) - PostgreSQL platform support: installation and usage
- [packages/ztd-query-postgres/docs/spec.md](packages/ztd-query-postgres/docs/spec.md) - How ZTD handles PostgreSQL SQL statements
- [packages/ztd-query-sqlite/README.md](packages/ztd-query-sqlite/README.md) - SQLite platform support: installation and usage
- [packages/ztd-query-sqlite/docs/spec.md](packages/ztd-query-sqlite/docs/spec.md) - How ZTD handles SQLite SQL statements
