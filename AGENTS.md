<!-- NOTE: You do not have permission to overwrite this file. Please ask a human operator to perform the changes for you. -->
# AGENTS

This file is for agents to understand the context of the project.

## Project Goal

Implement a Zero Table Dependencies (ZTD) mechanism for PHP 8.1+ using a PDO Proxy.
The goal is to enable testing without modifying the physical database by using CTEs to shadow tables and simulate writes.

## Core Concepts

- **CTE Shadowing**: Using `WITH` clauses to mock table data for SELECT queries.
- **Result Select Query**: Converting INSERT/UPDATE/DELETE queries into SELECT queries that return the data that would have been modified.

## Tech Stack

- PHP 8.1+
- MySQL, PostgreSQL, SQLite
- PHPStan (Level Max)
- PHP-CS-Fixer
- PHPUnit
- Infection
- PHP-Fuzzer

## Coding Rule

- Follow the fix instructions provided in lint error messages

## Packages

### packages/ztd-query-core

Core library for ZTD Query. Provides the foundational interfaces, session management, and query routing logic.
Contains `Session`, query classification interfaces, rewriter contracts, and schema abstractions.
No platform-specific or adapter code — those live in separate packages.

### packages/ztd-query-mysql

MySQL platform support for ZTD Query. Handles SQL parsing, classification, rewriting, error classification, schema reflection, and mutation resolution using phpmyadmin/sql-parser.
Provides `MySqlSessionFactory` for creating ZTD sessions with MySQL support.
Depends on ztd-query-php core. Includes fuzz testing for robustness (classify, rewrite, full).

### packages/ztd-query-postgres

PostgreSQL platform support for ZTD Query. Handles SQL parsing, classification, rewriting, error classification, schema reflection, and mutation resolution.
Provides `PgSqlSessionFactory` for creating ZTD sessions with PostgreSQL support.
Depends on ztd-query-php core. Includes fuzz testing for robustness (classify, rewrite, full).

### packages/ztd-query-sqlite

SQLite platform support for ZTD Query. Handles SQL parsing, classification, rewriting, error classification, schema reflection, and mutation resolution.
Provides `SqliteSessionFactory` for creating ZTD sessions with SQLite support.
Depends on ztd-query-php core. Includes fuzz testing for robustness (classify, rewrite, full).

### packages/ztd-query-pdo-adapter

PDO adapter for ZTD Query. Provides `ZtdPdo` (extends PDO) and `ZtdPdoStatement` (extends PDOStatement) that transparently apply ZTD rewriting via delegation pattern.
Depends on ztd-query-php core and ztd-query-mysql. Integration tests use MySQL Testcontainers.

### packages/ztd-query-mysqli-adapter

MySQLi adapter for ZTD Query. Provides `ZtdMysqli` (extends mysqli) and `ZtdMysqliStatement` (extends mysqli_stmt) that transparently apply ZTD rewriting via delegation pattern.
Depends on ztd-query-php core and ztd-query-mysql. Integration tests use MySQL Testcontainers.

### packages/sql-faker

Faker Provider for generating syntactically valid SQL statements for MySQL, PostgreSQL, and SQLite.
Based on official grammar definitions, can generate any statement type (DML, DDL, TCL, etc.) and SQL fragments (expressions, clauses, subqueries, CTEs).
Supports MySQL 5.6–9.1, PostgreSQL, and SQLite. Used for fuzz testing.

### packages/sql-fixture

Faker Provider for generating test fixture data from SQL schemas.
Parses CREATE TABLE statements and generates type-appropriate fake data using PHP-Faker.
Provides three usage modes: SQL string-based (`FixtureProvider`), PDO connection-based (`DatabaseFixtureProvider`), and DDL file directory-based (`FileFixtureProvider`).
Supports MySQL, PostgreSQL, and SQLite. Includes object hydration via `ReflectionHydrator`. Used for fuzz testing and integration tests.

### packages/phpstan-custom-rules

Project-specific PHPStan rules package shared across ztd-query packages.
Provides custom rules for code quality and consistency, including forbidden comments (`@phpstan-ignore*`, `//`), restrictions in test classes (no properties/constants/private methods), and source/unit test pairing checks.
Loaded from each package `phpstan.neon` via `vendor/k-kinzal/phpstan-custom-rules/extension.neon`.

## Documents

- [docs/ztd-mechanism.md](docs/ztd-mechanism.md) - Overview and design of the ZTD mechanism
- [docs/mysql-spec.md](docs/mysql-spec.md) - How ZTD handles MySQL SQL statements
- [docs/postgres-spec.md](docs/postgres-spec.md) - How ZTD handles PostgreSQL SQL statements
- [docs/sqlite-spec.md](docs/sqlite-spec.md) - How ZTD handles SQLite SQL statements
- [docs/sql-support-matrix.md](docs/sql-support-matrix.md) - Supported SQL statements and their status
