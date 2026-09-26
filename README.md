# ZTD Query PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

This repository is a PHP implementation of Zero Table Dependency (ZTD). It began with an [article](https://zenn.dev/mkmonaka/articles/c2413d99ae67bb) by M Sugiura ([@mkmonaka](https://zenn.dev/mkmonaka)): ZTD is their idea, we were impressed by it and wanted it in PHP, and we are grateful to the author.

It is also an experiment with AI agents. ZTD is complex, and this repository tests how AI agents can build something like it correctly.

## Packages

| Package | Description |
|---------|-------------|
| [bison-parser](packages/bison-parser/) | Parser for GNU Bison grammar files, producing a lossless syntax tree |
| [container](packages/container/) | Container definitions for testcontainers-php used across the repository |
| [lemon-parser](packages/lemon-parser/) | Parser for Lemon grammar files, producing a lossless syntax tree |
| [requirements](packages/requirements/) | Links source text, EARS specifications, and tests for PHP projects |
| [sql-catalog](packages/sql-catalog/) | Catalogs the SQL an application issues, by static analysis |
| [sql-faker](packages/sql-faker/) | Grammar-based SQL generator with a Faker provider |
| [sql-fixture](packages/sql-fixture/) | Faker provider for generating test fixture data from schemas |
| [sql-formatter](packages/sql-formatter/) | SQL formatter with layout presets, built on the sql-parser syntax tree |
| [sql-parser](packages/sql-parser/) | Lossless LALR(1) SQL parsers built from the official grammars |
| [sql-semantics](packages/sql-semantics/) | Typed statement models and schema binding: names, types, nullability, and value provenance |
| [sql-semantics-mysql](packages/sql-semantics-mysql/) | MySQL statement models and binding rules for sql-semantics |
| [sql-semantics-postgres](packages/sql-semantics-postgres/) | PostgreSQL statement models and binding rules for sql-semantics |
| [sql-semantics-sqlite](packages/sql-semantics-sqlite/) | SQLite statement models and binding rules for sql-semantics |
| [ztd-query-core](packages/ztd-query-core/) | Core library: session, shadow store, rewrite planning, and platform contracts |
| [ztd-query-mysql](packages/ztd-query-mysql/) | MySQL platform: SQL parsing, classification, rewriting, schema reflection |
| [ztd-query-mysqli-adapter](packages/ztd-query-mysqli-adapter/) | MySQLi adapter: drop-in `ZtdMysqli` / `ZtdMysqliStatement` |
| [ztd-query-pdo-adapter](packages/ztd-query-pdo-adapter/) | PDO adapter: drop-in `ZtdPdo` / `ZtdPdoStatement` |
| [ztd-query-postgres](packages/ztd-query-postgres/) | PostgreSQL platform: SQL parsing, classification, rewriting, schema reflection |
| [ztd-query-sqlite](packages/ztd-query-sqlite/) | SQLite platform: SQL parsing, classification, rewriting, schema reflection |

## Related Projects

- [k-kinzal/ztd-query-php-scenario](https://github.com/k-kinzal/ztd-query-php-scenario): AI-operated example and lightweight contract suite for ztd-query-php adapters.

## License

MIT License. See [LICENSE](LICENSE) for details.
