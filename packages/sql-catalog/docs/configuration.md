# Configuration

The configuration file stores the command line settings of a project, and registers function models. The command reads `.catalog.yaml` in the working directory when it exists; pass `--config FILE` to use another. The file is read as YAML data; nothing in it is executed except the function models it names.

## Example

```yaml
paths: [src, app]
exclude: [tests]
extensions: [pdo, mysqli]
reporter: html
output: catalog
function-models:
  'App\table_name': 'App\Catalog\TableNameModel'
```

With this file, `vendor/bin/sql-catalog` writes the HTML report of `src` and `app` into `catalog/`.

## Fields

Every field is optional.

| Key | Value | Description |
|-----|-------|-------------|
| `paths` | list | Files and directories to analyze, when none are given on the command line. |
| `exclude` | list | Same as `--exclude`. |
| `extensions` | list | Same as `--extension`. |
| `root` | string | Same as `--root`. Default the directory of the configuration file. |
| `output` | string | Same as `--output`. |
| `reporter` | string | Same as `--reporter`. |
| `fail-on` | string | Same as `--fail-on`. |
| `namespace`, `method`, `path`, `kind`, `table`, `sink` | list | Same as the [filter](cli.md#filters) options. |
| `severity` | string | Same as `--severity`. |
| `function-models` | mapping | Function names to models. See [function models](#function-models). |

`paths`, `root` and `output` are relative to the directory of the configuration file. Paths given on the command line stay relative to the working directory.

An option given on the command line replaces the configured value; for a list, the whole list is replaced. An empty list clears the configured value. Unknown keys and values of the wrong type exit with code `2`.

## Function models

A function model tells the analyzer what a PHP function returns, so that SQL built with it resolves. Map the function name to a model:

```yaml
function-models:
  array_fill: 'App\Catalog\ArrayFillModel'
  'App\table_name': 'App\Catalog\TableNameModel::evaluate'
```

| Model | Form |
|-------|------|
| Invokable class | `App\Catalog\TableNameModel`, constructed without arguments |
| Static method | `App\Catalog\TableNameModel::evaluate` |
| Function | `App\Catalog\table_name_model` |

The model is loaded through the project's Composer autoloader. It receives the evaluated arguments as a `list<SqlCatalog\Core\Evaluation\Domain>`, in source order, and returns a `Domain` or `null`:

```php
namespace App\Catalog;

use SqlCatalog\Core\Evaluation\Domain;

final class TableNameModel
{
    /** @param list<Domain> $arguments */
    public function __invoke(array $arguments): ?Domain
    {
        return Domain::literal('users');
    }
}
```

Function names are case-insensitive and include their namespace. A configured model takes priority over the built-in model of the same name. Returning `null` passes the call to the previous model, and then to ordinary analysis of the function's source. Return `Domain::unknown()` to report the result as unknown instead.

Built-in models cover `sprintf`, `vsprintf`, `implode`, `join`, `str_repeat`, `str_replace`, `strval`, `array_fill`, the trim and case functions, and a set of functions whose result is known only by type, such as `count`, `substr` and `json_encode`.

`array_fill` filled with `'?'` produces one placeholder, whatever the count, so that a placeholder list built with `implode` is catalogued as `IN (?)`:

```php
$marks = implode(',', array_fill(0, count($ids), '?'));
$db->prepare('SELECT * FROM users WHERE id IN (' . $marks . ')');
```
