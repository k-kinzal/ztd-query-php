# Dependency layers

The directory tree is the architecture boundary. Deptrac uses one namespace
collector per concept, without filename exclusions or per-class exceptions.

| Layer | Responsibility | Dependencies |
| --- | --- | --- |
| Core | Analysis, PHP source model, value evaluation, catalog values, SQL inspection, filtering, and extension/reporter contracts | Generic PHP parser |
| Extension/Pdo, Mysqli, Doctrine, Laravel, WordPress | Interpret one application database API through Core's sink and model contracts | Core |
| Reporter/Json, Text, Html | Render catalog values through Core's reporter contract | Core; Html also uses the generic formatting exception |
| Platform/MySql, PostgreSql, Sqlite | SQL spelling and report formatting for one database | Core contracts, matching parser/formatter platform, generic parser/formatter APIs |
| Facade | Configure and compose the built-in implementations; preserve convenient defaults | Core and concrete implementations |
| Cli | Parse arguments, run analysis, and write artifacts | Core and Facade |

Core has no dependency on an extension, reporter, database implementation, Facade,
or CLI. Implementations do not depend on peer implementations. In Core, the
existing Analysis, Catalog, Evaluation, Php, Source, Sql, Text, Type, Filter,
Extension, and Reporter directories retain their distinct responsibilities.

Core registries contain supplied implementations and supplied default names.
`Facade/\SqlCatalog\Facade\Builtins::extensions()` and
`Facade/\SqlCatalog\Facade\Builtins::reporters()` perform concrete registration.

Laravel's `Grammar` accepts `Core/Sql/Dialect`, and its models receive
`Core/Sql/Dialects`. Both SQL spelling and connection-to-dialect associations are
provided during composition. A library caller can register an application dialect
and select its identity through `Facade/AnalysisOptions`; the CLI validates the
built-in identities it composes.

HTML's gap-preserving formatter accepts a list of `Core/Reporter/SqlFormatter`
policies. Each platform can decline unsupported text. The chosen formatter is
passed through `ReportSite`, so listing pages and statement pages share the same
presentation policy. The built-in composition renders expanded SQL and preserves
the JSON schema and HTML artifact formats.

PHPStan's `forbiddenTermsByPath` rejects database words throughout `src/Core`,
including comments, strings, and documentation. It also rejects peer database
names in each platform. Deptrac and this lexical rule both run in `composer lint`.
