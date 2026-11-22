# MySQL result-label refinement

Direct construction requires an output label to be representable exactly. On MySQL 9.1.0, both quoted identifiers and quoted strings in the result alias position discard leading SQL whitespace. For example, `SELECT 1 AS ' lead', 2 AS 'trail '` reports the names `lead` and `trail `. A value that needs an explicit alias requires a label without leading ASCII SQL whitespace. Analysis drops the leading whitespace in that alias position.

A physical field can itself have a leading space. MySQL 8.4.7 preserves it in a wildcard expansion and an unaliased column read. The result constructor permits the label on a column reference, and the enclosing projection verifies an exact match to the owning physical field. The renderer emits the qualified read with its intrinsic label. The same field with a written alias follows the alias normalization rule.

This refines the local output-label condition in `server-projection-v1.json`. The identifier value remains applicable to schema objects and can carry leading whitespace when the object name rules allow it. No result observation, engine release, negative case or run budget is excluded. The server comparison covers the alias case and the list of negative cases includes direct construction with an unrepresentable label.
