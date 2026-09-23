# Traceability

## Problem and goals

A passing BDD suite demonstrates selected behavior; it does not show whether the
suite faithfully interprets its references or leaves parts of those references
unexamined. Specifications and their executable verification are the primary
entities. Requirements are optional upstream records, not a mandatory hierarchy.

| Need | Design | Acceptance evidence |
| --- | --- | --- |
| One YAML configuration and many definition files | Versioned YAML or experimental Markdown documents; one source or explicit `null` per definition | Configuration validation tests |
| Original text ↔ EARS specification ↔ tests | Stable IDs, exact evidence, optional requirement links, executable test references | Check and runner integration tests |
| Diverse sources | Source extension contract returning stable located units; HTML/XML CSS, JSONPath, Markdown CSS, text line ranges, RFC XML | Source adapter tests |
| Service integrations | Register a PHP class implementing the source contract; it owns retrieval and enumeration | Custom extension test and documented example |
| Optional upstream requirements | `kind: requirement`, specifications use `requirements: [ID]` | Graph validation and inherited evidence tests |
| Independent specifications | Explicit `origin: original` or `undocumented` and rationale | Lint and spec filters |
| Unsupported behavior | `status: unsupported` always needs a reason; never passes as implemented | Lint, coverage and spec tests |
| Related requirements/specifications and design | Typed requirement links, general related links, URL/text design records and metadata | Cross-file graph validation |
| Source integrity | Exact normalized quotation of a selected unit, optional pinned snapshot and SHA-256 | Changed/missing/ambiguous/out-of-scope evidence tests |
| Source coverage | Union of units enumerated by each URI + scope; no inferred website-wide denominator | Union and empty-scope tests |
| Verification | PHPUnit and Behat runner extensions; fresh JUnit results, positive test count, strict failures | Real subprocess integration tests |
| Navigation and authoring quality | `spec --no-test`, filters, `lint`, deterministic `format --check` | CLI integration tests |
| CI gates | Total, per-source and changed-unit thresholds; machine-readable baseline | Threshold and baseline tests |

## Coverage semantics

A source declares its complete selected scope independently of the specifications.
Its adapter enumerates units in that scope before any evidence is resolved. Each
unit has a stable location and text. HTML/XML/Markdown use selected DOM elements;
JSON uses selected values; text uses nonblank lines. Choose atomic statements
where practical: a paragraph is one unit, not a promise that every clause has been
specified. CSS selectors must not select both an ancestor and its descendant.

An evidence record selects exactly one unit and quotes its complete text. Only
Unicode whitespace is normalized. Partial quotations, empty matches, multiple
matches, text drift and selections outside the declared scope fail closed. The
same URI, format and unit location count once across overlapping definitions.
Conflicting content for the same unit is an error. Distinct locations with equal
text remain distinct units.

A valid specification accounts for its own evidence and evidence inherited from
its optional requirements. A requirement alone does not account for a unit.
Supported and reasoned unsupported specifications both count as *accounted*;
supported, unsupported-only and uncovered counts are reported separately.
Unsupported decisions do not imply implementation. Source-free specifications do
not increase source coverage. Test verification is a separate result; source
coverage never implies that tests passed. A zero-unit scope is an error, not 100%.

Checking quotation equality establishes provenance, not semantic equivalence.
EARS lint validates the clause syntax described in [lint rules](lint.md); human review still establishes whether the interpretation and test are
appropriate. This package never automatically labels unknown behavior unsupported.

## Documents and relationships

Definition `items` contain globally unique IDs, kind, statement, labels, category,
status, evidence, tests, requirements, related, design and open-ended metadata.
An item belongs to its definition's source. A source-free specification can inherit
provenance through requirements; otherwise it must declare origin and rationale.
Requirement links target requirements; related links may target either kind.
Dangling, duplicate and self links are errors. Requirements cannot have requirement
parents, so derivation cannot cycle. Related links may be reciprocal.

Configuration owns commands and their working directory; definitions own test
selection. Commands are argument arrays, never shell templates. PHPUnit references
are fully qualified `Class::method`; Behat references are `file.feature:line`.
Each distinct runner/target executes once, even when several specifications use it.
Every linked result must pass. Missing tests, skipped tests, malformed/missing
reports, no tests and process errors fail verification. Unsupported specifications
are reported without execution; supported specifications without tests fail.

`spec` also provides the filtered record browser. `--no-test` preserves the same
records and their linked-target denominators while showing unknown results (`-/N`).
Normal execution shows passed/linked targets (`N/N`), independently of how many
cases each data provider or scenario outline expands into. Executed case counts
remain available in JSON. Upstream requirements are visible as `not-applicable`.
See [CLI result semantics](cli.md#specification-results).

## Extensions and reproducibility

Source extensions receive the source definition and project directory and return
located units for a selector. They may use HTTP, local files or service APIs. Runner
extensions receive configuration and a target and return a structured result.
Both are registered by class name in YAML and loaded by a project autoloader or
explicit bootstrap. Built-ins are ordinary implementations of the same contracts.

A remote URI may have a local snapshot with a required SHA-256. Snapshots are untracked local caches, never bundled upstream documents.
Add `.requirements-cache/` to the consuming project’s `.gitignore`. On first use,
the URI is fetched and its digest verified before an atomic cache write. Subsequent
commands verify the cached bytes again; `--live` checks the remote resource instead. Reports say which
mode was used. Updating the source URI, digest, scopes and quotations is a reviewable change.
A mismatched download is not cached. A populated cache allows offline checks;
an empty cache needs network access. CI fetches upstream sources on demand.
HTTP has bounded time and size; XML parsing disables network entities. Secrets
belong in the extension environment, not definitions or reports.

## Baselines and CI

A versioned JSON baseline stores unit keys and semantic fingerprints only.
It does not duplicate upstream document text; reports printed with `--json` still
include selected unit text for review. Treat those reports as generated artifacts. The current report
compares each unit's content and the semantic fingerprints of its claiming records
(including statements, evidence, disposition, rationale and test references).
Added or changed units form the differential denominator. Removed units are listed
and rejected by default; scope removal needs an explicit `--allow-removed` decision.
An unchanged differential denominator is reported as not applicable and passes.
Malformed baselines are errors. The baseline must come from the trusted base
revision; a baseline regenerated in the same PR is not a trusted comparison.
This measures changed traceability units, not PHP line coverage.


## Implementing extensions

See [source and test extensions](extensions.md) for their responsibilities,
interfaces, complete implementation examples, registration and executable usage.

## Traceability of EARS validation

This package applies its own requirements tool to its EARS syntax validator.
[The definition](../requirements/ears.yaml) links the author's seven published
syntax templates, clause cardinalities and complex unwanted-behaviour rule to
positive and negative PHPUnit datasets. The scope is those nine selected units,
not the complete EARS site. Run from this package:

```console
php bin/requirements lint
php bin/requirements check
php bin/requirements coverage
php bin/requirements spec
```

The source is [Alistair Mavin's EARS description](https://alistairmavin.com/ears/).
No copy of that web page is distributed. A source check fetches the current page;
changes to the selected text require review. Quoted evidence and specifications
remain authored traceability records. See [lint](lint.md) for the syntax/semantics
boundary and the project's explicit authoring conventions.
