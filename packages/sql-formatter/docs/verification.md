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
- Compact has exact canonical-output cases across all 11 grammar releases,
  including equivalent spellings, keyword-shaped identifiers, mandatory syntax,
  operator association, executable comments, hints, nested comments, placeholders,
  and MySQL lexical modes. SQLite execution tests compare rows and explicit column
  aliases before and after compaction, including NULLs and precedence-sensitive
  arithmetic and predicates.
- The deterministic generated cases also require identical Compact output from
  each multiline layout. This checks the normal form used for generated-versus-
  serialized SQL comparisons without introducing a dependency on sql-semantics.
- Runtime reparsing verifies the selected grammar derivation and every token's
  kind and spelling. This is a structural check, not a database execution test or
  a proof of semantic equivalence for an arbitrary transformation.
  Compact uses canonical terminal and operand-nesting signatures instead of exact
  original grammar alternatives, and checks each parenthesis removal separately.
- PHPDoc examples run as doctests. ParaTest exercises independent worker processes.
- PHP-Fuzzer targets under `fuzz/` run nightly. The format targets hand the formatter
  arbitrary bytes behind a layout selector and accept only the parser's rejection, a
  verified result, and an idempotent second pass. The equivalence targets format
  statements sql-faker generates, per database and preset, and require MySQL,
  PostgreSQL, and SQLite to answer the original and the formatted text alike, rows
  and errors included; they replay sql-faker's seed corpora first, so every statement
  form of the default grammars is covered on each run. See `fuzz/README.md`.
- CI installs the locked development graph and tests PHP 8.1–8.5. Lint runs on
  PHP 8.3; Infection checks changed source lines on pull requests and the whole
  package on main.

Run `composer lint` and `composer test`. `composer test:unit` is available for
single-process debugging. `composer bench:quick` measures the full formatting
pipeline, including both parses, after parser tables have been loaded.
