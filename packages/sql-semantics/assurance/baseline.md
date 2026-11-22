# SemanticsIR baseline

The baseline is `9703938a21d69446897512513e61df47e1e7172c`. The affected packages are `sql-semantics` and its MySQL, PostgreSQL, and SQLite implementations. The inventory reads every named PHP class-like declaration in those four source trees, including declarations outside `Statement` and without public annotations. It records all declared public and magic method signatures, constructor promotion, declared properties, traits, and direct interfaces. Traits and interface relationships are visible in the inventory and require follow-through when classifying inherited paths. Undocumented types are kept as review candidates. Dynamic instantiation and callers of a factory require separate analysis.

| Baseline package | Named declarations | Documented public concrete classes and enums |
| --- | ---: | ---: |
| sql-semantics | 6 | 0 |
| sql-semantics-mysql | 1178 | 812 |
| sql-semantics-postgres | 1100 | 769 |
| sql-semantics-sqlite | 386 | 252 |

Run the following from the repository root with the SQLite package's locked development dependencies installed. The inventory reads the baseline source as data.

```sh
baseline=$(mktemp -d)
git archive 9703938a21d69446897512513e61df47e1e7172c packages/sql-semantics packages/sql-semantics-mysql packages/sql-semantics-postgres packages/sql-semantics-sqlite |
    tar -x -C "$baseline"
php packages/sql-semantics/assurance/inventory.php "$baseline" packages/sql-semantics-sqlite/vendor/autoload.php > baseline-inventory.json
```

The initial host had PHP 8.5.8 and PDO SQLite 3.54.1. Docker 29.7.2 exposed PostgreSQL 17.2 and MySQL 9.1.0 images. Image availability establishes no DB result. The initial executable contract requires a separate SQLite 3.47.2 build. Other releases and modes remain required in the scope ledger.

## Baseline publication and gaps

| Requirement | Baseline behavior and open obligation |
| --- | --- |
| P01 | `Semantics::analyze` returns an `Operation` when no diagnostic exists, even with dependent facts. Column-use nodes retain written names and separate facts can retain declaration objects. Complete publication needs resolved references and semantic values. |
| P02 | `new Operation` audits, derives, renders and reparses. The diagnostic list is rejected by the facade after that constructor succeeds. Public fragment constructors vary in how they check operand roles, allowed implementations and conditions. |
| P03 | `Operation` retains the complete analysis context. Its facts can retain tables and column declaration objects from it. Render layouts can retain source spelling. Completed IR needs to retain the observed result name as a value where spelling determined it. |
| P04 | Typed statement fragments render and derive through dialect-specific rules. Part of their meaning comes from analysis environments, unresolved facts and scope lookups. |
| P05 | `Snapshot` disables cloning, dynamic properties and PHP restoration for its users. The publication audit follows readonly properties and rejects array references, but permits types by namespace prefix and shape. It does not establish that every public fragment maintains its semantic invariants. |
| P06 | The checked root caches SQL. Rendering and parsing under the same rules establish correspondence with lowering. Shared rule errors can survive this check. There is no independent meaning comparison for each construct. |

Public construction includes `new Operation`, dialect facade `analyze` and `analyzeAll`, and public constructors and factories for statement parts. `inspect` is a callable diagnostic path with restricted documented visibility. Existing classes are inventory entries even when their use in a completed root is unproven. The production ledger links grammar productions to lowering dispatch. The grammar-based SQL fuzzers exercise schema and round trip targets through lowering. Their inputs start as SQL, the round trip sides share rules, and diagnostics or unsupported statements can stop an input before independent evaluation. Existing unit tests and fuzzers are retained as regression checks.

## Design and decision record

The initial implementation uses dialect-owned final value classes with closed operand unions, checked lists, a composition boundary and an explicit recursive emitter. The shared package defines the interfaces. Scope-sensitive references are slots of a checked local source whose external identity is schema-qualified. External table definitions, parse nodes and the analysis context are temporary inputs to analysis. The public analysis and direct construction paths meet at the same constructor. This first executable domain leaves the wider release and construct obligations in the required scope.

The trust review for the initial domain covers identifier escaping, SQLite integer literals and arithmetic, parameter numbering, projection semantics, scope membership, and the PHP representation. A generic validator would add a second language whose interpretation would also require review. Generation can reduce boilerplate later when there are several types with the same structural rules. A proof in another language would need a separately verified connection to the distributed PHP. No external model is counted as a PHP proof.

Treat changes to the contract, observations, case domain, budgets, oracle, and fault corpus as assurance changes. Keep the acceptance revision in a separate trusted checkout. The baseline and preliminary contract precede candidate implementation and require independent human approval. A candidate-controlled checkout and its own tests yield provisional evidence. The repository's current pull request workflows run candidate code and cannot by themselves protect an acceptance decision from changes in the candidate.

The per-constructor records in `contract-v1.json` inherit the initial domain's release, mode, and external interpretation. Each has all six P obligations with the shared Q mapping applied at the recorded role. The records also list the executable checks assigned to that role. Requirements elsewhere in the scope have incomplete records and remain open.

`grammar-scope.json` groups syntax rules by the statement entry alternatives that can reach them. The entry is `stmt` for PostgreSQL, `cmd` for SQLite, and `statement` or `simple_statement` for the respective MySQL grammar release. Per-root traversal and propagation of root sets compute the same groups. The bundle rebuilds the inventory from the candidate's pinned grammar artifacts and compares the complete record. The grouping retains empty alternatives and rules outside the selected statement entry. The related-domain count measures the association with reviewed scope records. Every semantic coverage claim still requires its own evidence.
