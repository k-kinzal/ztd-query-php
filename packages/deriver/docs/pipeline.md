# From captured source to candidates

Deriver answers a query in two groups of stages. The project stages run once when a session is opened: they expand the input through providers, parse every file, index the declarations, register the models, fix the snapshot identity and index the definitions. The query stages run once per query: they select the target, place its demand on the engine, expand the selected definition backwards and collect the alternatives into a candidate collection. No stage executes application code, and the artifact of every stage is immutable once produced.

## Project stages

Provider expansion runs the contributed providers once before anything is parsed. Entry point, declaration and environment providers add to the captured input and configuration, the contributions are recorded in the snapshot manifest, and the expanded input and configuration do not change for the rest of the session. A provider that contradicts an explicit environment value is rejected.

Parsing turns the bytes of one file and the target profile into a syntax tree with resolved names. The parser admits the file against the source limits, rejects grammar that the target version does not have, and records a malformed file as an INCOMPLETE_SOURCE diagnostic instead of a partial tree. Parsing the same bytes for the same profile always produces the same tree, so parsed files are reused by content hash.

Declaration scanning indexes the classes, functions, constants, properties and call sites of every parsed file without reading any function body. The declarations are available before a single body has been lowered, duplicate declarations are reported rather than silently chosen, and a call site is found by its syntax rather than by lowering the functions around it.

Lowering turns the syntax of one callable into a control flow graph of basic blocks and instructions. A body is lowered only when a query or an index demands it, each body is lowered once per snapshot, and a lowered body may be reused across snapshots when its file, declaration, target and lowering version are unchanged.

Model registration validates the contributed models, domains, intrinsics and state slots and resolves their precedence. Replacing a standard model requires an explicit declaration, repeated identifiers and ambiguous priorities are rejected, and the resulting registry is the only place a query consults for a model decision.

The snapshot identity is computed from the normalized sources, the registered model manifest, the environment, the dependency versions and the target profile. Two sessions with equal identities answer every query identically, and a change to any of those inputs changes the identity. File order does not.

Definition indexing records, for every lowered body, its definitions, predecessor edges, positions and return origins, together with the callers and declared property writes across the program. Building an index never derives the value of an instruction.

## Query stages

Target selection binds a query to one point of the program. Every reference the query carries must belong to the current snapshot and identify a lowered instruction, and the result names the owning callable, the kind of definition selected and the registers or storage point it selects. Later stages read the target and never inspect the query again.

Placing the demand creates one frame per entry declared by the query scope, or one source frame when the scope is symbolic, and asks the engine for the selected definition in each frame. The owner is a single candidate graph of alternatives; a symbol without captured source stays an unexpanded reference.

Backward expansion opens the selected definition into the definitions and inputs it depends on, under the query budget. Replacement rules and models are selected before any source body or input is demanded, unrelated calls and branches are not opened, equal dependencies share one node, and whatever cannot be opened remains in the graph as a residual with its reason.

Collection enumerates the alternatives of the candidate graph, folds equal values into one candidate, and keeps every derivation as its own evidence alternative under an observation root that names the target. The candidate limit, the residual dependencies and a stopped expansion are reported separately, and the collection is the only object the session returns for a query.

Export writes a candidate collection as JSON with four fields per candidate, and replay rederives the same query from the same captured source and configuration and compares the complete export. A changed value, derivation or manifest is reported as a mismatch.
