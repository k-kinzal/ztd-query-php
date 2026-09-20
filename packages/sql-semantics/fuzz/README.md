# Semantic property checks

`composer fuzz:smoke` runs 257 deterministic, independently executed SQLite
scenarios. Generated data varies nullable foreign references, matches, missing
parents, empty relations, join kinds, and aliases. Every inferred `NotNull`
output is checked against actual results, and occurrence lineage and COALESCE
facts are checked separately.

`composer fuzz:semantics` runs the same target continuously under PHP-Fuzzer.
`composer fuzz:semantics -- --max-runs=100` supplies a bounded fuzz budget.
Failures preserve the input for replay. Requires `ext-pdo_sqlite` for development;
the library itself never opens a connection. This is a semantic property target,
not a claim of coverage for the whole SQL grammar.
