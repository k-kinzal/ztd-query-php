# Verification

The SQL-string public API is tested with PostgreSQL, MySQL, and SQLite. Tests
cover schema construction and reuse, default namespaces and grammar context,
binding and alias visibility, self joins, nested outer joins, NULL provenance,
ON versus WHERE stages, stars and duplicate names, ordering, pagination,
declaration constraints, type promotion, source identity, and explicit rejection
of unsupported syntax. Each test declares its own inputs. Every source class has
a paired unit test and every public API class has an executable PHPDoc example.

`composer lint` runs strict autoload checks, PHP-CS-Fixer, PHPStan at max level
with all PHP-AI-Toolkit rules, PHPCompatibility for PHP 8.1+, LOC guard, directory
guard, and Deptrac. Rules are not suppressed or baselined.

`composer test` runs unit tests and documentation examples with ParaTest and the
AI reporter. `composer test:coverage` generates unit-test coverage for inspection.

`composer bench:quick` measures `Binder::bind()` on a self-join SQL string against
an already built schema. This includes SQL parsing and semantic binding. Parser
resource loading and schema construction happen before measurement.

Infection uses the default mutator set, includes uncovered code, and requires
80% MSI and 80% covered MSI. Run it with the Infection 0.35.4 CLI (installed
separately, as in the other packages) and a coverage driver:

```bash
infection --configuration=infection.json5 --with-uncovered --threads=4 --only-covering-test-cases
```

The CI workflow checks PHP 8.1 through 8.5 and runs lint, mutation testing, and the
benchmark smoke test. The library and these checks require no live database.

The [round-trip fuzz targets](../fuzz/README.md) reuse all three sql-faker seed
corpora and compare original and reconstructed SQL with the Compact formatter.
This is a stricter contract than the current binder implements: all three targets
currently find rejected statements, and `BoundSelect` cannot yet serialize its
own data. The fuzz workflow fails on these findings. Passing the existing unit
suite does not establish full-language structural coverage or round-trip support.
