# Lint rules

`requirements lint` validates every loaded document before any source retrieval or
test execution. All other commands run the same validation first. Failures identify
the file and, for JSON Schema violations, the instance pointer. Exit code is 2.
A configured bootstrap loads project code. Experimental Markdown validation runs
an external validator, but it does not fetch the requirement sources.

## Schemas and structure

The bundled [configuration schema](../schemas/config.schema.json) and
[definition schema](../schemas/definition.schema.json) use JSON Schema 2020-12.
Lint uses Opis JSON Schema to check required fields, scalar/map/list types, enums,
unknown fields, duplicate lists and reason requirements. Empty maps (`{}`) and
lists (`[]`) are distinct and remain distinct after formatting.

A `$schema` declaration selects a bundled schema URI or an additional local JSON
Schema file, resolved relative to the declaring document. Built-in schemas resolve
offline. Local schemas add constraints; they cannot replace or weaken the bundled
schema. Unknown remote URIs fail explicitly instead of silently bypassing validation.
Local schemas should be self-contained; remote reference retrieval is not enabled.
The declaration is optional for existing YAML files. See [format](format.md) for
editor configuration and experimental Markdown's document-schema profile.

## EARS syntax

Specifications follow [the patterns and ruleset published by EARS co-author
Alistair Mavin](https://alistairmavin.com/ears/). Requirements (`kind: requirement`)
may contain ordinary prose. A clause validator checks the five basic patterns and
combinations of them:

| Pattern | Form |
| --- | --- |
| Ubiquitous | `The <system> shall <response>` |
| State driven | `While <preconditions>, the <system> shall <response>` |
| Event driven | `When <trigger>, the <system> shall <response>` |
| Optional feature | `Where <feature>, the <system> shall <response>` |
| Unwanted behaviour | `If <trigger>, then the <system> shall <response>` |
| Complex | `While <preconditions>, When <trigger>, the <system> shall <response>` |

The generic clause order is preconditions, trigger, system response. There may be
zero or many preconditions, zero or one trigger, one system name and one or many
responses. Complex unwanted behaviour keeps the `If`–`Then` pair. Conditions,
system names and responses must contain text. Missing commas between clauses,
missing `the`/`shall`, empty slots, reversed clauses, repeated triggers, missing or
unexpected `then` and multiple system clauses are rejected.

This implementation uses the following explicit authoring conventions to make
natural-language slot boundaries deterministic:

- Keywords are case insensitive. Final punctuation is optional; EARS does not
  require a final period in its syntax templates.
- A complex optional feature clause comes first, before any `While` clauses and
  the optional `When` or `If` trigger. Multiple preconditions may be combined in
  one `While` clause or written as successive `While` clauses.
- Separate responses share one `shall` and can be joined with `and`. Write separate
  specifications for separate systems.
- Reserved delimiters begin a comma-separated clause. Other commas remain part of
  a natural-language slot. Quote literal keyword text with balanced single/double
  quotes or backticks; apostrophes within words are allowed. Unquoted `shall` is
  reserved for the system clause, and `then` in a condition is rejected.
- Slots are natural language. The validator does not parse their internal English
  grammar, prove temporal meaning, detect every ambiguous sentence, or establish
  equivalence to source text and tests. Those checks require review.

[The EARS traceability definition](../requirements/ears.yaml) connects these syntax
rules to source templates and executable acceptance tests. Project conventions
above refine the published patterns; they are not additional claims about the
EARS standard.

## Cross-file traceability

After schema and EARS validation, lint checks unique source/item IDs, references,
source-free origins and reasons, requirement/specification relationships, runner
names, thresholds and registered extension interfaces. Unsupported specifications
need reasons. Independent items cannot simultaneously claim sourced evidence.
Dangling and self references fail. `related` edges can be reciprocal.

`lint` does not establish source availability or quote equality; use `check`.
It does not run tests or establish implementation coverage; use `spec`.
