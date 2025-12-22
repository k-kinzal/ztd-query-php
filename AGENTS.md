# AGENTS

This file is for agents to understand the context of the project.

## Project Goal
Implement a Zero Downtime Deployment (ZTD) mechanism for PHP 8.1+ using a PDO Proxy.
The goal is to enable testing without modifying the physical database by using CTEs to shadow tables and simulate writes.

## Core Concepts
- **CTE Shadowing**: Using `WITH` clauses to mock table data for SELECT queries.
- **Result Select Query**: Converting INSERT/UPDATE/DELETE queries into SELECT queries that return the data that would have been modified.

## Tech Stack
- PHP 8.1+
- MySQL
- PDO
- k-kinzal/testcontainers-php
- PHPStan (Level Max)
- PHP-CS-Fixer
- PHPUnit

## Packages

### packages/sql-faker
Faker Provider for generating syntactically valid MySQL SQL statements.
Based on MySQL's official Bison grammar (sql_yacc.yy), can generate any MySQL statement type (DML, DDL, TCL, etc.) and SQL fragments (expressions, clauses, subqueries, CTEs).
Supports MySQL 5.6–9.1. Used for fuzz testing.
