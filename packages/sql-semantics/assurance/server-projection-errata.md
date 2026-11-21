# MySQL result-label refinement

Direct construction requires an output label to be representable exactly. On MySQL 9.1.0, both quoted identifiers and quoted strings in the result alias position discard leading SQL whitespace. For example, `SELECT 1 AS ' lead', 2 AS 'trail '` reports the names `lead` and `trail `. The result constructor refuses a decoded label starting with an ASCII SQL space character. Analysis drops the leading whitespace when naming the result, so the SQL inputs remain accepted and the observed label is retained.

This refines the local output-label condition in `server-projection-v1.json`. The identifier value remains applicable to schema objects and can carry leading whitespace when the object name rules allow it. No result observation, engine release, negative case or run budget is excluded. The server comparison covers the alias case and the list of negative cases includes direct construction with an unrepresentable label.
