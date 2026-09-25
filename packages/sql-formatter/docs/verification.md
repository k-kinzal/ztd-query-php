# Verification

The checks serve different purposes:

- Exact expected outputs compare all four presets across every supported grammar
  release. These catch visually wrong layouts that still parse correctly.
- Preservation and idempotence datasets cover queries, nested queries, CTEs, joins,
  aggregates, CASE, range predicates, writes, DDL, comments, placeholders, strings,
  quoted identifiers, and dialect-specific syntax.
- Deterministic sql-faker generation exercises complete statements from all 11
  grammar releases with all four presets. The generator starts at each dialect's
  statement rule; parser and formatter failures fail the test. Generated cases do
  not replace the exact layout expectations.
- Unit tests exercise lexical spacing, block widths, comment handling, grammar
  annotations, and syntax signatures independently.
- Runtime reparsing verifies the selected grammar derivation and every token's
  kind and spelling. This is a structural check, not a database execution test or
  a proof of semantic equivalence for an arbitrary transformation.
- PHPDoc examples run as doctests. ParaTest exercises independent worker processes.
- CI installs the locked development graph and tests PHP 8.1–8.5. Lint runs on
  PHP 8.3; Infection checks changed source lines on pull requests and the whole
  package on main.

Run `composer lint` and `composer test`. `composer test:unit` is available for
single-process debugging. `composer bench:quick` measures the full formatting
pipeline, including both parses, after parser tables have been loaded.
