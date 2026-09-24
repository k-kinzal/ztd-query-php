# Source models in extensions

An `ExtensionInterface` declares database sinks and known globals. It remains
sufficient for APIs that receive SQL directly. An extension can additionally
implement `SqlCatalog\Extension\Model\ModelProviderInterface` and return a
`ModelSet` from `models(ModelContext $context)`.

The registry instantiates providers only for enabled extensions, separately for
each analysis. `ModelContext` supplies the indexed PHP declarations, selected
SQL dialect and the shared, bounded callback evaluator. No application or
framework classes need to be loaded.

## Calls producing values or SQL fragments

`ModelSet::calls` contains functions with the signature
`Closure(CallContext): ?Domain`. The context contains the PHP call AST, its
evaluated arguments, receiver or static class, matched sink and evaluation
services. These functions are registered through
`Analysis\FunctionModel\Registry::registerCall()`, extending the same registry
that holds named function models. Later registrations run first. Return `null`
to decline a call;
return a `Domain` to model its result. A domain can contain a SQL string,
fragments with unresolved holes, alternatives, arrays or object state. If every
context-aware model declines, named function models (including YAML-configured
models) and ordinary source analysis remain available. Registrations are copied
for each analysis, so enabling an extension does not affect later runs that
leave it disabled.

For example, this extension models an application's `today_sql()` helper:

```php
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Extension\Model\CallContext;
use SqlCatalog\Extension\Model\ModelContext;
use SqlCatalog\Extension\Model\ModelProviderInterface;
use SqlCatalog\Extension\Model\ModelSet;
use SqlCatalog\Extension\PdoExtension;

final class ApplicationSql implements ModelProviderInterface
{
    public function name(): string { return 'application'; }
    public function description(): string { return 'Application SQL helpers'; }
    public function sinks(): array { return []; }
    public function globals(): array { return []; }

    public function models(ModelContext $context): ModelSet
    {
        return new ModelSet(calls: [
            static fn (CallContext $call): ?Domain =>
                $call->node instanceof \PhpParser\Node\Expr\FuncCall
                && $call->name === 'today_sql'
                    ? Domain::literal('CURRENT_DATE')
                    : null,
        ]);
    }
}

$analyzer = new Analyzer(new ExtensionRegistry([
    new PdoExtension(), new ApplicationSql(),
]));
$catalog = $analyzer->analyzeSource([
    'query.php' => '<?php $pdo = new PDO("sqlite::memory:"); $pdo->query("SELECT " . today_sql());',
], new AnalysisOptions(['pdo', 'application']));
// The statement is SELECT CURRENT_DATE.
```

The same hook handles instance methods, static calls and constructors. Models
must inspect the AST before accepting a call and interpret named or unpacked
arguments themselves. Known but unsupported overloads should return an opaque
domain rather than claim an exact value. A model accepting a call owns its
side effects through the context's environment and object memory; the core's
conservative unknown-call invalidation runs only when all models decline.

## Calls executing modelled queries

For an execution whose SQL is derived from receiver state or other expressions:

1. Declare a `SinkSpec` with `SinkRole::Modelled` and an extension-specific
   `model` key, such as `application.query`.
2. Register a `QueryModelInterface` under that key in `ModelSet::queries`.
3. Return the required PHP expressions from `inputs($call)`.
4. Compile their derived domains in `statements($call, $values)` and return
   `QueryOutput` objects containing SQL and ordered bindings.

The core derives all requested expressions together with its ordinary backward
slicer. It retains caller and branch correspondence, then adds the original
provenance and budget flags to every output. A model may emit several statements
or alternatives. `QueryOutput` can additionally mark truncated or combined
results. SQL and bindings occupy fixed output positions; `sqlParameter` and
`valuesParameter` are not needed on a modelled sink. A missing registration
produces an incomplete statement.

Use the existing domain operations to combine SQL fragments, so unresolved data
and alternatives survive compilation. Turning a domain into a PHP string too
early would discard those distinctions.

## Mutable objects and framework types

When a model provider is enabled, the core retains potential object mutations
in its slices. `ObjectTerm` can carry an allocation identity and an immutable
`ArrayTerm` snapshot. Models store updated snapshots through the call context's
`environment->objects()`; aliases read the same allocation, while cloned and
branched environments retain their separate state. Unknown calls open the state
of tracked objects they receive, including objects inside arrays or callbacks.

`ModelSet::classRelations` accepts predicates `(string $class, string $expected):
bool` for inheritance the extension knows but the analyzed source does not
contain. These supplement ordinary source-declared inheritance.

## Laravel's registration

`LaravelExtension::models()` registers its call transformer, query compiler and
framework type relations through this same API. All Illuminate names, Eloquent
metadata, supported methods, scopes and SQL grammar rules live under the Laravel
extension. The core refers only to the model contracts; it does not construct or
select Laravel implementations. Architecture checks forbid analysis code from
depending on the Laravel layer.
