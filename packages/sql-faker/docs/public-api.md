# Public API and executable examples

The consumer entry points are `SqlFaker\MySqlProvider`,
`SqlFaker\PostgreSqlProvider`, and `SqlFaker\SqliteProvider`. They register SQL
formatters with a supplied Faker generator and also support direct method calls.
Each provider and every public method it declares has `@visibility public` and an
executable PHPDoc example. Inherited Faker methods follow Faker's own contract.

| Surface | Consumer contract |
| --- | --- |
| Provider constructors | Supply a Faker generator and optionally a supported database version; unknown versions throw `RuntimeException`. |
| Provider formatters | Generate statements, fragments, lexical tokens, or constrained scenarios. Named arguments specify depth or token bounds. |
| Each dialect's `StatementRule` enum and cases | Select the start rule passed to `sql()`. The existing `StatementType` alias remains supported and denotes the same cases. |
| `Grammar\GenerationException` | Catch a failure to derive the requested SQL. |
| `Grammar\LexicalException` | Catch a failure to realize or round-trip a generated token sequence. |
| `Grammar\LexicalCatalogException` | Catch a malformed or inconsistent installed lexical catalog. |

The exception classes remain runtime exceptions. Their named construction helpers
carry `@visibility root`: callers catch these types or use the inherited exception
constructor, while the package owns its detailed failure-message factories.

The generation engine, generator factory, generation plans, compiler AST, grammar
objects, and dialect implementation collaborators have restricted `@visibility`
scopes. Existing narrower `namespace` and `parent` scopes remain in effect; shared
implementation types use `root`, meaning `SqlFaker` and its descendants. Tests may
exercise these types through the toolkit's default `Tests` namespace exemption.

Resource ingestion is a separate boundary. The existing profile builders,
`LexicalProfileSource`, `LexicalProfileCheck`, `LexicalProfileWriter`,
`LexicalCatalog`, `LexicalCatalogShape`, and `LexicalWitnessShape` still accept
arbitrary decoded input before validating its shape. Their unrestricted signatures
are retained, without declaring them supported consumer API. This permits the
toolkit's `ForbidInternalMixedTypeRule` to distinguish raw input from the typed
internal collaborators. An absent visibility tag is not a promise of API stability.

## What the examples promise

Examples create their own Faker generator. Names are fully qualified because
doctest evaluation does not inherit the source file's imports. Random examples
seed Faker after constructing the provider, then assert the statement or lexical shape instead of pinning an
incidental spelling or comment layout. Fixed numeric bounds produce exact values. Unsigned big integers discard leading
zeroes, so their final length can be shorter than the sampled digit count.
Seeds reproduce a run within the same package and Faker versions, not an output
format guaranteed across upgrades.

`maxDepth: 0` selects shortest productions immediately. Depth is not a byte limit,
and generated SQL can contain whitespace and comments. Optional PostgreSQL and
SQLite clauses can produce an empty string; those examples assert the empty
result explicitly. A syntax fragment or statement still depends on a real schema
and server state for semantic validity.

## Running examples

```sh
composer test
composer doctest
vendor/bin/phpunit --testsuite doctest --no-extensions
vendor/bin/phpunit --testsuite doctest --filter 'Choose a supported database version'
```

`composer test` selects all configured suites through ParaTest. `composer test:unit`
continues to select unit tests only. Mutation testing deliberately selects the
unit suite; the normal CI PHP 8.1–8.5 test matrix executes documentation examples
alongside it. There is no separate doctest CI job or chained duplicate execution.
The toolkit's AI PHPUnit reporter remains enabled.

`phpunit.xml.dist` registers the installed
`Toolkit\Doctest\DoctestExtension`, scans the production `src` root, and loads
`vendor/k-kinzal/php-ai-toolkit/src/Doctest/DoctestSuite.php` directly. No custom
bootstrap is needed: Composer also autoloads the three `StatementType` aliases.

The current toolkit source scanner handles classes and methods but does not
collect enum declarations or enum cases. `tests/Doctest/EnumExamplesTest.php`
supplies only those statement-enum PHPDocs to the toolkit's own `ExampleExtractor`
and `ExampleExecutor`. It uses the source AST to retain the declaration's location,
names each example, and fails if an enum or case lacks executable documentation.
It runs in the same doctest suite, including with extensions disabled. Remove this
bridge when the installed toolkit suite collects both enums and their cases.

## Enforcing documentation and access

The existing toolkit PHPStan extension enables both `RequireExampleOnPublicApiRule`
and `EnforceVisibilityScopeRule`; neither is disabled or baselined. Public intent
is explicit on individual declarations, including methods and enum cases. An
inline-only `@example description` does not satisfy the example rule: code must
appear on indented following lines or in an executable PHP fence.

The visibility rule checks written class references inside PHPStan's analysed
paths, with the configured namespace exemptions. It is static analysis, not PHP
runtime access control. For example, a consumer namespace in those paths may
reference `MySqlProvider`, but cannot reference `Provider\SqlGeneratorFactory`.

Provider PHPDoc now contains substantially more executable examples. Its LocGuard
policy allows 1,100 physical file lines and 1,050 physical class lines, while
retaining the existing 350 code-line limit, 10-line method limit and complexity
limit of 1. Other source-size policies are unchanged.
