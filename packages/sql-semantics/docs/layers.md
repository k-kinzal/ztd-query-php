# Dependency layers

`Core` owns semantic values, schema construction, binding, provenance, and the
contracts used to interpret a language. It depends on the independent statement model and sql-parser's generic parser and token/tree contracts. Its syntax vocabulary is supplied as semantic
roles; it does not select a database, parser implementation, or grammar release.

`Core/Policy` separates identifier, type, schema, query, and grammar-vocabulary
policies. `Core/Dialect` identifies a language and supplies its `Core/Platform`.
Applications can implement those contracts without changing the binder.

`Platform/MySql`, `Platform/PostgreSql`, and `Platform/Sqlite` provide independent
implementations of those policies and compose their matching syntax parser.
A platform may use Core's binding operations, but cannot use another platform.

`Facade/Dialect` retains the built-in enum and selects the corresponding platform.
Semantic values, AST helpers, and binding operations live under `Core`.

`Facade/Semantics` composes `Core/Analysis/Analyzer`, which lowers the transient
syntax tree using the selected construction map. `Statement` and the generated
`Statement/Model` namespace own the data and SQL writers. They have no dependency
on Core, the facade, or any sql-parser class. The Deptrac `Statements` layer
includes both `src/Statement` and `resources/models` and has an empty ruleset.
Only the lowering phase knows both the parser and the statement model.

Deptrac checks namespace boundaries with one collector per layer. PHPStan's
`forbiddenTermsByPath` additionally rejects database names anywhere in Core,
including comments, strings, and documentation. Platform paths reject names of
other databases. Both checks run as part of `composer lint`.

The library has no CLI layer. Benchmark and development scripts are consumers.
