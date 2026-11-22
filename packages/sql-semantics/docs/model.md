# Semantic values

`SemanticsIR` has a language profile identifier and an SQL representation. A dialect owns the concrete statement, expression, source and effect types. Inspect a concrete value using its fields to compose or traverse a statement.

A projection stores its ordered result list with labels. A reference addresses an occurrence and a slot in the corresponding source. Two written names for the same bound column produce the same reference. Distinct occurrences of a table have distinct occurrence identities. A source exposes the relation identity and its relevant named columns.

A complete value can be constructed directly from the corresponding checked types. The containing statement validates scope and relationships that require the assembled structure. Analysis constructs the same types. Catalog declarations, parser objects and intermediate binding information are temporary inputs to that construction.

See [supported statements and roles](semantics-ir.md) and [catalog inputs](contexts.md).
