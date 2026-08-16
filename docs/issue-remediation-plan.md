# Issue-zero remediation plan

This document is the source of truth for reducing the open issue backlog to
zero without treating repeated symptoms as independent bugs.

Snapshot: 2026-08-16. There are 157 open issues in the repository. Every open
issue is assigned exactly one primary root-cause group below. Issue #157 has a
secondary dependency because it reports two independent behaviours.

## Triage rules

1. One pull request fixes one root cause. A pull request may close several
   issues only when the same invariant and implementation defect explain all of
   them.
2. Every fix starts with an adapter-level regression test that compares native
   database behaviour with ZTD behaviour. Parser-only assertions are not
   sufficient for semantic bugs.
3. Every fix adds the missing SQL feature family to correctness fuzzing. A
   single saved corpus string is useful for regression, but it does not replace
   generation of the surrounding language family.
4. SQL expressions are opaque to statement-structure code. Clause discovery
   must use a lossless token stream with nesting and scope, never a regular
   expression that can terminate on a keyword inside an expression or subquery.
5. Shadow values retain both their PHP value and reflected SQL type until the
   dialect value codec renders them. String interpolation is not a value codec.
6. Mutation application consumes typed before/after row deltas. It must not
   parse SQL expression strings or infer the original row identity from an
   updated primary key.
7. An issue is closed only after the regression test, relevant unit suites,
   PHPStan, formatting, and the expanded correctness fuzz target pass.

## Root-cause map

| ID | Root cause | Primary issues | Required fix |
| --- | --- | --- | --- |
| RC-01 | Statement structure is recovered with regexes or context-free string splitting, so nested expressions change clause boundaries. | #9, #11, #14, #47, #50, #51, #57, #58, #59, #72, #84, #93, #96, #98, #100, #103, #115, #116, #119, #131, #132, #134, #136, #140, #142, #144, #147, #148, #165, #171, #174, #176, #177, #179 | Introduce a dialect-aware, lossless token stream and top-level clause model. Preserve expression slices verbatim and migrate DML projection builders away from regex boundaries. |
| RC-02 | Relation binding is based on text occurrence instead of SQL scope, so CTEs, derived tables, aliases, qualified names, and correlated references bind incorrectly. | #12, #13, #24, #28, #52, #56, #71, #73, #74, #79, #81, #102, #104, #109, #110, #111, #135, #137, #139, #143, #168, #172, #173 | Build a scoped relation graph, allocate collision-free internal CTE names, merge `WITH [RECURSIVE]` structurally, and rewrite only resolved physical-table references. |
| RC-03 | INSERT result projection is matched by textual column names and missing schema values are represented as NULL. | #18, #20, #21, #31, #40, #49, #76, #83, #99, #117, #127, #145, #166, #175, #178 | Model INSERT source rows by ordinal, resolve DEFAULT/generated/identity values from schema metadata, and preserve SELECT modifiers and aliases when producing result rows. |
| RC-04 | Prepared SQL is rewritten at prepare time and placeholder identity is not preserved through rewrites. | #22, #23, #45, #61, #62, #63, #68, #75, #80, #85, #87, #95, #101, #106, #108, #113, #118, #125, #128, #129, #146, #167 | Add a prepared-query blueprint. Rebuild the shadow plan for every execution and maintain an explicit original-to-rewritten placeholder map, including generated fixture parameters. |
| RC-05 | Shadow values are interpolated as strings and the type model loses native type details. | #5, #6, #15, #19, #33, #90, #92, #151, #155, #170 | Add per-dialect typed value codecs for text, boolean, integer widths, exact numeric, float, binary, arrays, enum, bit, domains, and NULL. Preserve native type identity in `ColumnType`. |
| RC-06 | Mutations use result rows as both identity and replacement and report matched rows as changed rows. | #7, #65, #94, #130, #141, #150 | Return typed before/after row deltas with stable internal identities, apply UPDATE/DELETE atomically, and calculate dialect-specific affected-row counts. |
| RC-07 | Conflict handling understands primary keys only and evaluates upsert expressions in PHP with regexes. | #16, #17, #30, #41, #42, #55, #64, #91, #105, #112, #114, #126, #133, #152, #153, #169 | Represent unique/FK constraints and conflict targets in schema metadata, let the database evaluate conflict expressions, and apply constraint/cascade changes as a mutation set. |
| RC-08 | The schema registry is a startup snapshot rather than a transactional catalog of tables, views, temporary objects, generated columns, and partitions. | #27, #54, #66, #122, #123, #124, #156, #157, #159 | Add catalog object kinds and dependency metadata; update them on simulated DDL; resolve views/partitions/temp schemas; invalidate derived metadata deterministically. |
| RC-09 | Database transactions do not snapshot shadow state. | #120, #149 | Add transaction and named-savepoint snapshots for both the shadow store and catalog, wired to PDO/MySQLi transaction APIs and SQL TCL statements. |
| RC-10 | A rewrite plan has no result contract for native DML result surfaces. | #32, #77, #121 | Add a result contract carrying RETURNING rows, generated identity, affected rows, and normal fetch behaviour; expose it consistently through PDO and MySQLi. |
| RC-11 | Statement policy conflates safe introspection, unsupported physical operators, and unimplemented write simulation. | #43, #78, #107, #154, #158, #160, #161, #162, #163, #164 | Introduce typed capabilities (`safe passthrough`, `shadow rewrite`, `shadow simulation`, `unsupported`) and feature-specific handling for storage-bound operators and bulk/procedural statements. |
| RC-12 | A rewrite plan and mutation target only one table. | #26, #29, #44 | Make rewrite plans contain an ordered mutation set and produce per-target deltas for multi-table UPDATE/DELETE/TRUNCATE. |
| RC-13 | Internal simulation failures escape the Session error boundary and bypass configured unknown-schema behaviour. | #2, #39 | Replace raw runtime failures with typed domain exceptions and resolve unknown schema before any shadow-store mutation. |
| RC-14 | CTAS derives schema from returned data rows rather than result metadata and source lineage. | #36, #37 | Add result-column metadata/lineage to CTAS plans so empty results still create typed columns. |

Secondary dependency: #157 is owned by RC-08 for attached catalog state and
also requires RC-02 for `main.table` relation binding.

## Stacked pull requests

The stack is linear so each pull request has a reviewable base. Behavioural
changes and their regression/fuzz additions stay in the same pull request.

| Stack | Branch intent | Root cause closed | Required prevention |
| --- | --- | --- | --- |
| 00 | issue taxonomy and closure gates | Planning baseline | Every open issue has one primary root cause, an owning stack, and explicit merge/closure criteria. |
| 01 | typed simulation errors | RC-13 | Error-boundary contract tests and unknown-schema correctness generation. |
| 02 | semantic SQL fuzz feature model | Cross-cutting | Replace the five handwritten correctness variants with grammar-backed, schema-aware feature families and record minimized seeds with feature metadata. |
| 03 | typed shadow value codecs | RC-05 | Type-family round trips and generated boundary values for every reflected native type. |
| 04 | lossless SQL structure | RC-01 | Balanced/nested clause properties generated from SQLFaker expression productions. |
| 05 | scoped relation binding | RC-02 | Generated nesting, alias, derived-table, CTE, recursive CTE, and qualified-name combinations. |
| 06 | prepared execution blueprints | RC-04 | Differential prepared/re-execution sequences and placeholder-location generation. |
| 07 | INSERT source semantics | RC-03 | Generated VALUES/DEFAULT/INSERT-SELECT projection families, including modifiers and generated identities. |
| 08 | typed mutation deltas | RC-06 | Native-vs-ZTD state and affected-row differential checks for key changes, no-op updates, ordering, and limits. |
| 09 | constraint and conflict engine | RC-07 | Generated PK/UNIQUE/FK schemas and conflict/cascade operation sequences. |
| 10 | transactional shadow state | RC-09 | Stateful BEGIN/SAVEPOINT/ROLLBACK/COMMIT model-based fuzzing. |
| 11 | result contracts | RC-10 | RETURNING/fetch/identity/affected-row adapter contract tests. |
| 12 | catalog lifecycle | RC-08 | Generated DDL/DML/query sequences over tables, views, temporary schemas, generated columns, and partitions. |
| 13 | CTAS metadata lineage | RC-14 | Empty and non-empty CTAS differential tests with computed and source columns. |
| 14 | multi-target mutation sets | RC-12 | Generated multi-target DML with per-table post-state comparison. |
| 15 | statement capability matrix | RC-11 | Each classified statement family must have a generated witness and an explicit safe/simulated/unsupported outcome. |

## Merge and closure policy

- Pull request 00 targets `main`. Pull request 01 targets stack 00, and each
  later pull request targets the preceding stack branch until its dependency
  merges, then is retargeted to the new remaining base.
- Pull requests are drafts until all checks relevant to their changed packages
  pass.
- PR descriptions list `Fixes` only for issues whose complete acceptance
  criteria are satisfied by that PR. Derivative issues are not closed early.
- After each merge, the issue query is rerun and the remaining count is checked
  against this map. The final gate is an empty `is:issue is:open` result.
