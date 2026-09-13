# Development checks

The quality gates follow the SQLFaker implementation in repository PRs #292–#313.
Run commands from `packages/ztd-query-postgres`.

## Locked dependencies

The default `composer.lock` is the PHP 8.5 development graph. Run `composer install`
and `composer check-platform-reqs` before the checks. PHP AI Toolkit is locked to
its latest available `dev-main` revision at adoption time.

This package retains its existing PHP 8.1–8.5 test matrix and PHPUnit major support.
The matching locked graphs are:

| PHP | Lock file | PHPUnit |
| --- | --- | --- |
| 8.1 | `composer.lock.php-8.1` | 10.5 |
| 8.2 | `composer.lock.php-8.2` | 11 |
| 8.3 | `composer.lock.php-8.3` | 12 |
| 8.4 | `composer.lock.php-8.4` | 12 |
| 8.5 | `composer.lock` | 13 |

SQLFaker uses one PHP 8.1 / PHPUnit 10 graph whose locked ParaTest version supports
PHP through 8.3. Here, each matrix leg installs a committed graph compatible with
its actual PHP runtime, including all development dependencies. No platform
requirement is ignored.

For example, in a checkout running PHP 8.3:

```sh
composer config platform.php 8.3.0
cp composer.lock.php-8.3 composer.lock
composer validate --strict --no-check-all --no-check-publish
composer install
composer check-platform-reqs
composer test
```

Select the analogous graph for PHP 8.1, 8.2 or 8.4. These selection commands modify
the local manifest and default lock; keep those local selections out of commits.
Dependency upgrades must regenerate all five graphs and pass the runtime matrix.
The four PHPUnit XML files match the majors actually installed by those graphs;
`tests/run.php` selects the installed major's configuration.

## Commands

| Check | Command |
| --- | --- |
| Parallel unit tests and executable API examples | `composer test` |
| Unit tests only | `composer test:unit` |
| Executable API examples only | `composer doctest` |
| SQLFaker property tests (also in PHP 8.5 CI) | `composer test:fuzz` |
| Strict coding standards | `composer format:check` |
| PHPStan max, strict and toolkit rules | `composer phpstan` |
| PHP 8.1–8.99 syntax compatibility | `composer compat` |
| Source size and complexity | `composer loc-guard` |
| Directory and filename structure | `composer tree-guard` |
| Architecture and unassigned classes | `composer deptrac` |
| Full benchmarks, including PR CI | `composer bench` |
| Short benchmark during development | `composer bench:quick` |
| Documentation site | `composer docgen` |
| Documentation diff against main | `composer docgen:diff` |

See [fuzz/README.md](fuzz/README.md) for native fuzz targets, their contracts,
coverage and replay instructions. PHPBench uses the toolkit defaults and retains
the parser and rewrite workloads.

Infection runs as the pinned 0.35.4 PHAR in GitHub Actions. Whole-source gates are
80% MSI / covered MSI; changed-line gates are 85% / 85%. Whole-source CI also
rejects skipped mutants. PHP-Fuzzer, PHPBench and Infection stay outside the
normal unit/doctest suite.

## Public API documentation

`PgSqlSessionFactory` is the adapter entry point. The parser, query guard,
rewriter, schema parser, error classifier, identifier quoter, value renderer and
structured conflict target
also expose documented contracts for custom composition. Their public entry
points carry `@visibility public` and runnable PHPDoc examples. Internal helpers
retain their package visibility. Existing production class names and method
signatures remain compatible; unit test namespaces follow directory paths.

DocGen builds from the repository root with the PostgreSQL package selected,
including the same content and architecture in normal and diff mode. Main and
PR preview publication retain SQLFaker's existing site and place this package
under `ztd-query-postgres/`.
