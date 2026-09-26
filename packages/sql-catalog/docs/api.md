# PHP API

The command line is built on `SqlCatalog\Facade\Analyzer`. Use it directly to analyze source in tests or tools, or to register custom extensions and function models.

```php
use SqlCatalog\Facade\Analyzer;

$catalog = (new Analyzer())->analyzePaths(['src']);

foreach ($catalog as $entry) {
    echo $entry->site->display(), ' ', $entry->kind->value, ' ', $entry->sql(), "\n";

    foreach ($entry->placeholders as $placeholder) {
        echo '  ', $placeholder->token, ' = ', $placeholder->value?->display() ?? '(unbound)', "\n";
    }
}
```

## Analyzer

| Method | Description |
|--------|-------------|
| `new Analyzer(?ExtensionRegistry $extensions = null, ?Registry $functionModels = null)` | An analyzer with the given extensions and [function models](#function-models), or the built-in ones. |
| `analyzePaths(array $paths, ?AnalysisOptions $options = null, string $root = '.', array $excluded = [])` | Analyzes files and directories. `$root` and `$excluded` work like `--root` and `--exclude`. Throws `SourceScanException` when a path cannot be read. |
| `analyzeSource(array $sources, ?AnalysisOptions $options = null)` | Analyzes source text, keyed by the path to report. |
| `withConfiguration(Configuration $configuration)` | A new analyzer with the function models of a [configuration file](configuration.md), loaded with `Configuration::load($path)`. |

Both analyze methods throw `UnknownExtensionException` when the options name an extension that is not registered. A file that fails to parse is reported in `Catalog::problems()` instead.

```php
$catalog = (new Analyzer())->analyzeSource([
    'users.php' => '<?php function all(PDO $db) { return $db->query("SELECT id FROM users"); }',
]);
$catalog->entries()[0]->sql(); // 'SELECT id FROM users'
```

## Options

```php
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Facade\AnalysisOptions;

$options = new AnalysisOptions(
    extensions: ['pdo', 'laravel'],
    budget: new EvaluationBudget(maxSteps: 50000, maxDepth: 6, maxLoopPasses: 2),
    dialect: 'mysql',
);
```

| Argument | Default | Description |
|----------|---------|-------------|
| `extensions` | `['pdo', 'mysqli']` | The extensions to enable, by name. |
| `budget` | `new EvaluationBudget()` | The [analysis limits](analysis.md#limits). |
| `dialect` | `null` | `mysql`, `pgsql` or `sqlite`, for framework query builders. |

## Catalog

`Catalog` is iterable and countable. Entries are ordered by file, line and ID.

| Method | Returns |
|--------|---------|
| `entries()` | `list<CatalogEntry>` |
| `problems()` | `list<AnalysisProblem>`, each with `file` and `message`. |
| `find(string $id)` | The entry with the ID, or `null`. |
| `filter(callable $keep)` | A catalog with the entries for which `$keep(CatalogEntry)` is true. |
| `merge(Catalog $other)` | A catalog with the entries and problems of both. |

`CatalogEntry` has the same information as a [JSON statement](format.md#json):

| Member | Description |
|--------|-------------|
| `id`, `tables`, `correlated`, `through` | As in JSON. |
| `kind` | A `StatementKind`. |
| `site` | A `CallSite` with `file`, `line`, `function` and `sink`; `display()` gives `file:line`. |
| `placeholders` | `list<Placeholder>`, each with `token`, `position`, `name` and `value`, a `ValueDomain` or `null`. `ValueDomain` has `type`, `values`, `exhaustive`, `origins` and `display()`. |
| `findings` | `list<Finding>`, each with `rule` (`FindingRule`), `severity` (`Severity`) and `message`. |
| `sql()` | The SQL, with `{$}` for unknown values. |
| `isExact()`, `resolution()`, `searchClosed()` | See [resolution](analysis.md#resolution). |
| `severity()` | The highest severity of the findings, or `Severity::Info`. |
| `hasFinding(FindingRule $rule)` | Whether the entry has a finding of the rule. |

To select entries the way the command line filters do, use `SqlCatalog\Core\Filter\CatalogFilter`:

```php
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Filter\CatalogFilter;

$risky = (new CatalogFilter(namespaces: ['App\Repository'], minimumSeverity: Severity::High))->apply($catalog);
```

## Reports

```php
use SqlCatalog\Facade\Builtins;

$artifacts = Builtins::reporters()->get('json')->render($catalog);
$json = $artifacts->get('catalog.json');
```

`render()` returns `CatalogArtifacts`. `all()` gives every file as name to contents, and `get($name)` one file. To add a format, implement `ReporterInterface` with `name()`, `description()` and `render(Catalog $catalog): CatalogArtifacts`, and register it on a `ReporterRegistry`.

## Function models

Register [function models](configuration.md#function-models) as callables:

```php
use SqlCatalog\Core\Analysis\FunctionModel\Registry;
use SqlCatalog\Facade\Analyzer;
use SqlCatalog\Core\Evaluation\Domain;

$models = Registry::withBuiltins();
$models->register('App\table_name', static fn (array $arguments): Domain => Domain::literal('users'));

$catalog = (new Analyzer(functionModels: $models))->analyzePaths(['src']);
```

A later registration takes priority over earlier ones and over the built-in model. `Registry::withBuiltins()` includes the built-in models; `new Registry()` starts empty.

## Extensions

Pass an `ExtensionRegistry` to recognise other database APIs. See [writing an extension](extensions.md#writing-an-extension).
