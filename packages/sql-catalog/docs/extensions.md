# Extensions

An extension tells the analyzer which calls send SQL to a database. `pdo` and `mysqli` are enabled by default; enable others with `--extension` or `extensions` in the [configuration](configuration.md). Enabling an extension replaces the default list, so name every extension you need:

```console
vendor/bin/sql-catalog --extension pdo,doctrine src/
```

## Built-in extensions

| Extension | Covers |
|-----------|--------|
| [`pdo`](extensions/pdo.md) | `PDO` and `PDOStatement`. Enabled by default. |
| [`mysqli`](extensions/mysqli.md) | `mysqli` and `mysqli_stmt`, as methods and as `mysqli_*` functions. Enabled by default. |
| [`doctrine`](extensions/doctrine.md) | Doctrine DBAL `Connection` and `Statement`. |
| [`laravel`](extensions/laravel.md) | Raw SQL through `DB` and `Connection`, the Query Builder and Eloquent. |
| [`wordpress`](extensions/wordpress.md) | `wpdb`, including SQL built with `wpdb::prepare()`. |

Each page lists the calls the extension recognises, with the sink ID that `site.sink` reports and `--sink` selects, and what the extension does not cover.

## Receivers

A method call is recognised by its name and the class of its receiver. The class is taken from parameter, property and return types, from `new`, and from what an assignment in the analyzed source leaves in the variable. Subclasses are recognised when their declaration is in the analyzed paths; a subclass declared only in `vendor/` is not known to extend the driver class.

| Receiver | Reported |
|----------|----------|
| A class the enabled extensions name, or a subclass of one | As a statement with the extension's sink ID. |
| A known class that no enabled extension names | Not reported. |
| A class that cannot be determined | With the sink `unmatched`, the resolution `not-analyzed` and the finding `call-not-analyzed`. |

`global $db;` says nothing about the class of `$db`. Document it with an `@global` or `@var` tag on the declaration, or rely on an extension that names the global, as `wordpress` does for `$wpdb`:

```php
/** @global PDO $db */
global $db;
```

A function or method call between the declaration and the database call might reassign the global, so after it the class is unknown again.

## Writing an extension

Implement `SqlCatalog\Core\Extension\ExtensionInterface` and register it on an `ExtensionRegistry`. Custom extensions are used through the [PHP API](api.md); the command line uses the built-in ones.

```php
use SqlCatalog\Facade\AnalysisOptions;
use SqlCatalog\Facade\Analyzer;
use SqlCatalog\Core\Extension\ExtensionInterface;
use SqlCatalog\Facade\Builtins;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;

final class AppDatabaseExtension implements ExtensionInterface
{
    public function name(): string { return 'app'; }
    public function description(): string { return 'App\Database'; }

    public function sinks(): array
    {
        return [
            new SinkSpec('app.select', SinkCallKind::Method, 'App\Database', 'select', SinkRole::Query, sqlParameter: 0, valuesParameter: 1),
        ];
    }

    public function globals(): array
    {
        return ['db' => 'App\Database'];
    }
}

$registry = Builtins::extensions();
$registry->register(new AppDatabaseExtension());
$catalog = (new Analyzer($registry))->analyzePaths(['src'], new AnalysisOptions(['pdo', 'app']));
```

| Method | Returns |
|--------|---------|
| `name()` | The name that selects the extension. |
| `description()` | The text shown by `--list-extensions`. |
| `sinks()` | A `list<SinkSpec>`, one for each call the extension recognises. |
| `globals()` | Global variable names, without `$`, mapped to their class. |

`SinkSpec` describes one call:

| Argument | Description |
|----------|-------------|
| `id` | The sink ID reported in `site.sink`. |
| `callKind` | `SinkCallKind::Method`, `StaticCall` or `FunctionCall`. |
| `receiverType` | The class the call is made on, or `null` for a function. Subclasses match too. |
| `name` | The method or function name, matched without case. |
| `role` | What the call does; see below. |
| `sqlParameter` | Zero-based position of the SQL argument. |
| `valuesParameter` | Position of an array of bound values. |
| `valuesFrom` | Position where variadic bound values start. |
| `nameParameter`, `valueParameter` | Positions of the name and value of a single bound parameter. |
| `kind` | The `StatementKind` the call implies, when the SQL does not say. |
| `handleType` | The class a preparing call returns, so that later binds find the statement. |
| `model` | The query model key of a `Modelled` call. See [query models](#query-models). |

| Role | The call |
|------|----------|
| `Query` | Runs the SQL argument. |
| `Prepare` | Prepares the SQL argument and returns a handle. |
| `Execute` | Runs a prepared handle, optionally with bound values. |
| `Bind` | Binds values to a prepared handle. |
| `Compose` | Returns SQL built from its arguments, like `wpdb::prepare()`. |
| `Modelled` | Runs SQL that a query model builds from other expressions, such as builder state. |

## Source models

An extension can also implement `SqlCatalog\Core\Extension\Model\ModelProviderInterface`, whose `models(ModelContext $context): ModelSet` supplies:

| `ModelSet` argument | Description |
|---------------------|-------------|
| `calls` | Call models: `Closure(CallContext): ?Domain`. |
| `queries` | Query models, keyed by the `model` of a `Modelled` sink. |
| `classRelations` | `Closure(string $class, string $expected): bool` predicates for inheritance the analyzed source does not contain, such as framework base classes. |

`ModelContext` gives the index of the analyzed declarations, the selected `dialect`, and the evaluator for callbacks. Models are created for each analysis, only for enabled extensions. No application or framework class is loaded.

### Call models

A call model returns the value of a call, or `null` to decline it. `CallContext` holds the call's AST `node`, its evaluated `arguments`, the `receiver` or `className`, the function `name`, the matched `sink`, and the `environment`. The latest registration runs first; when every call model declines, [function models](configuration.md#function-models) and ordinary analysis are used.

```php
use PhpParser\Node\Expr\FuncCall;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Extension\Model\CallContext;
use SqlCatalog\Core\Extension\Model\ModelContext;
use SqlCatalog\Core\Extension\Model\ModelProviderInterface;
use SqlCatalog\Core\Extension\Model\ModelSet;

final class ApplicationSql implements ModelProviderInterface
{
    public function name(): string { return 'application'; }
    public function description(): string { return 'Application SQL helpers'; }
    public function sinks(): array { return []; }
    public function globals(): array { return []; }

    public function models(ModelContext $context): ModelSet
    {
        return new ModelSet(calls: [
            static fn (CallContext $call): ?Domain => $call->node instanceof FuncCall && $call->name === 'today_sql'
                ? Domain::literal('CURRENT_DATE')
                : null,
        ]);
    }
}
```

With this extension enabled, `$pdo->query('SELECT ' . today_sql())` is catalogued as `SELECT CURRENT_DATE`.

- Check the AST before accepting a call, and handle named and unpacked arguments yourself.
- For an overload you do not support, return `Domain::unknown()` rather than a guess.
- Build SQL with `Domain` operations such as `concat()` and `union()`. Converting to a PHP string early loses gaps and alternatives.
- A model that accepts a call is responsible for its side effects on objects in `$call->environment->objects()`.

### Query models

For a call whose SQL is built from state, such as a query builder:

1. Declare a `SinkSpec` with `SinkRole::Modelled` and a `model` key, such as `app.query`.
2. Register a `QueryModelInterface` under that key in `ModelSet::queries`.
3. Return the expressions the SQL depends on from `inputs($call)`.
4. Build the statements from their evaluated values in `statements($call, $values)`, as a list of `QueryOutput(sql: Domain, bindings: Domain)`.

The analyzer evaluates the inputs together, so alternatives stay paired, and adds the call site's provenance to every output. Set `truncated` or `combined` on a `QueryOutput` when the model had to cut or combine alternatives. A `Modelled` sink without a registered model produces an incomplete statement.

When a provider is enabled, the analyzer tracks objects passed through method calls. Models read and store an object's state with `environment->objects()`; aliases share the state, clones and branches get their own. A call no model handles makes the state of the objects it receives unknown.

The `laravel` extension is built on this API.
