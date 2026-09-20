# Verification

The public example is tested with PostgreSQL, MySQL, and SQLite parser trees.
Tests cover binding and alias visibility, self joins, nested outer joins, NULL
provenance, ON versus WHERE stages, stars and duplicate names, ordering,
pagination, declaration constraints, type promotion, source identity, and
explicit rejection of unsupported syntax. Every source class has a paired unit
test and every public API class has an executable PHPDoc example.

`composer lint` runs strict autoload checks, PHP-CS-Fixer, PHPStan at max level
with all PHP-AI-Toolkit rules, PHPCompatibility for PHP 8.1+, LOC guard, directory
guard, and Deptrac. Rules are not suppressed or baselined.

`composer test` runs unit tests and documentation examples with ParaTest and the
AI reporter. `composer test:coverage` generates unit-test coverage for inspection.
`composer fuzz:smoke` checks 257 reproducible generated scenarios against an
independent SQLite execution oracle. The PHP-Fuzzer target uses the same oracle
continuously. See [fuzzing](../fuzz/README.md).

`composer bench:quick` measures semantic analysis of an already parsed self-join
query against an already built catalog. Parsing, resource loading, and schema
construction are excluded from that measurement.

Infection uses the default mutator set, includes uncovered code, and requires
80% MSI and 80% covered MSI. Run it with the Infection 0.35.4 CLI (installed
separately, as in the other packages) and a coverage driver:

```bash
infection --configuration=infection.json5 --with-uncovered --threads=4 --only-covering-test-cases
```

The initial implementation passed 283 unit/documentation cases with 529
assertions on PHP 8.1 and 8.5. The whole-source mutation run generated 1,346
mutants: MSI 81.58%, covered MSI 84.33%, with no errors, timeouts, or skipped
mutants. These are measurements of this revision, not guarantees about future
coverage or support for unmodeled SQL.

The CI workflow checks PHP 8.1 through 8.5 and runs lint, property checks,
PHP-Fuzzer, mutation testing, and the benchmark smoke test. The library has no live database dependency; SQLite is
used only by development verification.
