# Lint

`requirements lint` checks the configuration and every definition file without downloading sources or running tests. Every other command runs the same checks first. Lint exits with `2` and names the file, and for schema errors the JSON pointer, of each problem.

## What lint checks

| Check | Fails when |
|-------|------------|
| Schema | A required key is missing, a value has the wrong type, or a key is unknown. The [configuration schema](../schemas/config.schema.json) and [definition schema](../schemas/definition.schema.json) are JSON Schema 2020-12. |
| IDs | Two sources or two items share an ID. |
| Links | A `requirements`, `related` or Markdown link points to an unknown item or to the item itself, or a `requirements` link points to a specification. |
| Item rules | An unsupported item has no `reason`, an item without a source has no `origin` and `reason`, or such an item claims evidence. |
| Runners and extensions | A test names an unknown runner, or a registered class does not implement its extension interface. |
| Thresholds | A threshold is outside 0–100 or names an unknown source. |
| EARS | A specification statement does not follow an [EARS](#ears) pattern. |
| Markdown | A Markdown definition does not follow the [Markdown layout](definitions.md#markdown-definitions), or a badge contradicts its alt text. |

Lint does not check that quotations match their source; that is `check`. It does not run tests; that is `spec`.

## EARS

Specifications must follow the patterns of the [Easy Approach to Requirements Syntax](https://alistairmavin.com/ears/). Requirements may be any prose.

| Pattern | Form |
|---------|------|
| Ubiquitous | `The <system> shall <response>` |
| State driven | `While <precondition>, the <system> shall <response>` |
| Event driven | `When <trigger>, the <system> shall <response>` |
| Optional feature | `Where <feature>, the <system> shall <response>` |
| Unwanted behaviour | `If <trigger>, then the <system> shall <response>` |
| Complex | `While <precondition>, when <trigger>, the <system> shall <response>` |

Clauses come in this order: an optional `Where`, any number of `While`, at most one `When` or `If`, then the system and its responses. Keywords are case insensitive and the final period is optional. Several responses share one `shall` and are joined with `and`; write a separate specification for each system.

A comma followed by a keyword starts the next clause; other commas belong to the text. To use a keyword such as `shall` or `then` as a word inside a clause, quote it with quotes or backticks.

The check covers the form only. Whether a specification says what its source means, and whether its tests prove it, still needs review.
