# Verification

Structural analysis is tested with MySQL, PostgreSQL, and SQLite, including
stored programs, CTEs, windows, DML, DDL, transaction commands, and administrative
commands. Round-trip tests compare Compact output. Separate tests construct
models without a parser, vary their data, and verify the resulting SQL. Weak
references verify that lowering releases parser nodes and tokens. Deptrac checks
the entire generated model namespace and prohibits parser dependencies there.

The schema-dependent binding API is also tested with all three databases. Tests
cover schema construction and reuse, default namespaces and grammar context,
binding and alias visibility, self joins, nested outer joins, NULL provenance,
ON versus WHERE stages, stars and duplicate names, ordering, pagination,
declaration constraints, type promotion, source identity, and explicit rejection
of unsupported syntax. Each test declares its own inputs. Every source class has
a paired unit test and every public API class has an executable PHPDoc example.

Run the checks below from each of the four package directories.

`composer lint` runs strict autoload checks, PHP-CS-Fixer, PHPStan at max level
with all PHP-AI-Toolkit rules, PHPCompatibility for PHP 8.1+, LOC guard, directory
guard, and Deptrac. Rules are not suppressed or baselined.

`composer test` runs unit tests and documentation examples with ParaTest and the
AI reporter. `composer test:coverage` generates unit-test coverage for inspection.

In the common runtime, `composer bench:quick` measures `Binder::bind()` on a self-join SQL string against
an already built schema. This includes SQL parsing and semantic binding. Parser
resource loading and schema construction happen before measurement. Each database
package benchmarks complete statement analysis and SQL reconstruction.

Infection uses the default mutator set, includes uncovered code, and requires
80% MSI and 80% covered MSI. Run it with the Infection 0.35.4 CLI (installed
separately, as in the other packages) and a coverage driver:

```bash
infection --configuration=infection.json5 --with-uncovered --threads=4 --only-covering-test-cases
```

The CI workflow checks PHP 8.1 through 8.5 and runs lint, generated-resource
verification, and the benchmark smoke test for every package. Separate runtime
installation jobs verify each database without development dependencies or other
SQL Semantics database packages. These checks require no live database.

The [round-trip fuzz targets](packages.md#fuzzing) reuse all three sql-faker seed
corpora and compare original and reconstructed SQL with the Compact formatter.
Every generated statement must succeed; analysis, printing, formatting, and
equality failures are findings. The default corpora contain 2,135 MySQL, 2,639
PostgreSQL, and 294 SQLite plans. PR checks replay these plans and add mutations;
nightly and manual runs extend the mutation budget. Finite runs provide evidence
for the property; exhaustive structural coverage comes from compiling a model
for every alternative in each shipped grammar, without fallback values.

Run `composer build:models` from a database package to regenerate its checked-in
model classes and construction maps from the official grammar releases. CI verifies that regeneration produces
no changed or additional resources. PHPStan checks the generated models as well
as handwritten source; generation is not an exemption from type checking.
