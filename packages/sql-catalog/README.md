# SQL Catalog

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

Catalog the SQL statements a PHP application can issue, by reading its source.

## Overview

`sql-catalog` reads PHP source and reports every statement the code can send to a
database: the statement text, the tables it names, the values bound to its
placeholders, and where in the source it is issued. Nothing runs, and no database
is needed.

A statement assembled from a constant, an enum, a property or a method in another
file resolves to the statement it really is. One assembled from a value the
analyzer cannot follow is reported as the shape it has, with the gap marked and
traced back to where the value came from — which is how a query built out of
request input is told apart from one that is merely dynamic.

```
$ sql-catalog --output catalog/ src/
catalog/catalog.json
```

## Requirements

- PHP 8.1 or higher
- [nikic/php-parser](https://github.com/nikic/PHP-Parser) ^5.0

## Installation

```bash
composer require --dev k-kinzal/sql-catalog
```

## Usage

### Command line

```bash
# Print what the sources issue
vendor/bin/sql-catalog src/

# Write a JSON catalog, for committing and diffing
vendor/bin/sql-catalog --output catalog/ src/

# Write an HTML report
vendor/bin/sql-catalog --output catalog/ --reporter html src/

# Recognise a framework's own database calls
vendor/bin/sql-catalog --output catalog/ --extension laravel --dialect mysql src/

# Keep only the writes one namespace issues
vendor/bin/sql-catalog --namespace 'App\Repository' --kind insert,update,delete src/

# Fail the build when a value reaches the statement text from outside the program
vendor/bin/sql-catalog --severity high --fail-on high src/
```

Run `sql-catalog --help` for the full option list, `--list-extensions` for the
database APIs this build recognises and `--list-reporters` for the output formats.

### From PHP

```php
use SqlCatalog\Analyzer;

$catalog = (new Analyzer())->analyzePaths(['src']);

foreach ($catalog as $entry) {
    echo $entry->site->display(), ' ', $entry->kind->value, ' ', $entry->sql(), "\n";

    foreach ($entry->placeholders as $placeholder) {
        echo '  ', $placeholder->token, ' = ', $placeholder->value?->display() ?? '(unbound)', "\n";
    }
}
```

## What it reports

Given this repository:

```php
enum Status: string
{
    case Active = 'active';
    case Banned = 'banned';
}

final class UserRepository
{
    private const TABLE = 'users';

    private string $order = 'name';

    public function __construct(private PDO $pdo)
    {
    }

    public function findByStatus(Status $status): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM ' . self::TABLE . ' WHERE status = :status ORDER BY ' . $this->order,
        );
        $statement->execute([':status' => $status->value]);

        return $statement->fetchAll();
    }
}
```

the catalog holds:

```
src/UserRepository.php:27  SELECT  93af735295ad
  in App\UserRepository::findByStatus via pdo.prepare
  SELECT id FROM users WHERE status = :status ORDER BY name
  resolved
  :status = 'active'|'banned'
```

The table name came from a class constant, the sort column from a property
default, and the bound value from the enum the parameter is typed as — so the
parameter is reported as the two strings the column can actually hold, not as
`string`.

A statement the analyzer cannot fully resolve keeps its resolved parts and marks
the rest:

```
src/Search.php:17  SELECT  742968908c6b
  in App\Search::run via pdo.query
  SELECT id FROM users WHERE name = '{$}'
  external-input
  [MEDIUM] dynamic-sql 1 value(s) are spliced into the statement text rather than bound.
  [HIGH] external-input A value from external input reaches the statement text.
```

## Reported findings

| Rule | Severity | Meaning |
|------|----------|---------|
| `external-input` | high | A value spliced into the statement text comes from a superglobal or an input-reading function. |
| `dynamic-sql` | medium | A value is spliced into the statement text instead of being bound. |
| `placeholder-count-mismatch` | medium | The statement binds a different number of values than it has placeholders. |
| `unresolved-sql` | low | The statement text did not resolve far enough to read what it does. |
| `analysis-incomplete` | low | A cycle or an analysis budget stopped the search before it closed. |

## Extensions

An extension names the calls that reach a database. Raw SQL arguments use the
shared value analysis. Extensions can also register call transformations and
statement compilers through a framework-neutral source model API.

| Extension | Covers |
|-----------|--------|
| `pdo` | `PDO` and `PDOStatement`, including drop-in subclasses such as `ZtdPdo` |
| `mysqli` | `mysqli` and `mysqli_stmt`, in both object and procedural form |
| `doctrine` | Doctrine DBAL connections and prepared statements |
| `laravel` | Raw SQL, Query Builder and Eloquent execution calls |
| `wordpress` | `wpdb`, including the statements `wpdb::prepare()` interpolates |

`pdo` and `mysqli` are enabled by default. Enable Laravel explicitly and select
its SQL grammar with `--extension laravel --dialect mysql` (`pgsql` and `sqlite`
are also available). The analyzer reconstructs supported builder operations
without booting Laravel or loading the application's classes. Unknown effects
remain visible as incomplete statements. See [Laravel support](docs/laravel.md)
for supported operations, configuration and limitations.

Implement `SqlCatalog\Extension\ExtensionInterface` and register it on an
`ExtensionRegistry` to recognise an API this package does not ship. An extension
also says which global variables hold its handle, which is what makes the
`global $wpdb;` idiom readable as the database calls that follow it. The
analyzer reads an `@global` or `@var` tag documenting the declaration first, so
an application that annotates its own globals needs no extension at all.

Implement the optional `ModelProviderInterface` to register functions that turn
PHP call ASTs and evaluated values into values, SQL fragments or complete
statements. Laravel uses this same API. See [source models in extensions](docs/extensions.md)
for the registration contract and an application-specific example.

## Catalog configuration

The command automatically reads `.catalog.yaml` in the working directory.
Store the catalog's source paths, exclusions, output, extensions and function
models there:

~~~yaml
paths: [src, app]
exclude: [vendor, tests]
extensions: [pdo, mysqli]
reporter: html
output: catalog
~~~

Then run `vendor/bin/sql-catalog`. Use `--config=other.yaml` (or `-c`) to select
another catalog configuration. YAML is read as data; PHP configuration files
are not executed.

Paths, output directories and the reporting root in YAML are relative to the
configuration file's directory. The reporting root defaults to that directory.
Explicit CLI settings override the corresponding YAML settings; CLI source
paths replace the configured paths, and CLI lists replace the configured list.
CLI filesystem paths remain relative to the working directory.

Supported keys are:

| Keys | Value |
|------|-------|
| `paths`, `extensions`, `exclude` | Lists of strings |
| `namespace`, `method`, `path`, `kind`, `table`, `sink` | Lists of filter values, with the same meaning as the CLI options |
| `output`, `reporter`, `root`, `fail-on`, `severity` | Non-empty strings |
| `function-models` | A mapping of PHP function names to model classes or callable names |

An empty list clears that configured list. Unknown settings and invalid values
are reported as configuration errors. Help and extension/reporter listings do
not load the configuration. See [examples/catalog.yaml](examples/catalog.yaml).

## Function models

Every supported PHP function, including `sprintf`, `implode`, `join`,
`str_repeat`, `count` and `array_fill`, uses the same `register()` API.

The built-in `array_fill` model selects a representative singleton array when
its third argument resolves to `'?'`. The `implode`/`join` model then produces
one placeholder, including when values pass through intermediate variables:

~~~php
$size = count($ids);
$items = array_fill(0, $size, '?');
$marks = implode(',', $items);
$db->prepare('SELECT * FROM users WHERE id IN (' . $marks . ')');
~~~

This is catalogued as `SELECT * FROM users WHERE id IN (?)` by default.
No count is inferred or enumerated; even a known count or zero yields the same
representative. Other `array_fill` values remain unresolved. Resolution and
binding checks operate on the modeled SQL shape, so the representative does not
establish the runtime number of bound parameters.

To override a built-in or model an application helper, name a Composer-autoloaded
invokable class, static method or function in `.catalog.yaml`:

~~~yaml
function-models:
  array_fill: 'App\Catalog\ArrayFillModel'
  'App\table_name': 'App\Catalog\TableNameModel::evaluate'
~~~

Invokable classes are constructed without arguments. Each model receives a list
of evaluated `Domain` arguments in source order and returns `?Domain`. A model
can supply a constant, an array or partially known text using the same domain
types as the built-ins. Function names are case-insensitive and retain their
namespace.

From PHP, register a callable directly:

~~~php
use SqlCatalog\Analysis\FunctionModel\Registry;
use SqlCatalog\Analyzer;
use SqlCatalog\Evaluation\Domain;

$models = Registry::withBuiltins();
$models->register('App\\table_name', static fn (array $arguments): Domain => Domain::literal('users'));
$catalog = (new Analyzer(functionModels: $models))->analyzePaths(['src']);
~~~

Later registrations take priority, including over built-ins. Returning `null`
defers to the preceding model, then ordinary source analysis. Return
`Domain::unknown()` to explicitly replace a built-in result with an unknown
value. Models interpret calls without executing the analyzed application.

`$analyzer->withConfiguration(Configuration::load('.catalog.yaml'))` returns
another analyzer with that file's function models applied; command settings
such as output and filtering are used by the CLI. The original analyzer and
subsequent CLI runs keep their own registrations.

## Reporters

| Reporter | Writes | Purpose |
|----------|--------|---------|
| `json` | `catalog.json`, `catalog-schema.json` | A deterministic document with the JSON Schema that describes it; two runs of the same source produce the same bytes, so the diff of a pull request reads as the change in the SQL an application issues |
| `html` | `index.html`, `statements.html`, `tables.html`, `namespaces.html`, `files.html`, `findings.html`, `tables/`, `classes/`, `files/`, `statements/`, `assets/` | A site of linked pages laid out as the routes to a statement: by the table it names, by the namespace and class that issue it, by the file it is written in, or by what the analysis reported on it. Every table, class, file and statement has a page of its own, every listing can be narrowed on the page, and a search covers all of them |
| `text` | `catalog.txt` | One block per statement, for reading in a terminal |

Statement pages include the PHP source around the database call, with line numbers
and the call line highlighted. **View full source** opens the file at that line,
so you can follow SQL construction and execution without an IDE, including calls
that are unresolved or were not analyzed. Files that fail PHP parsing are linked
from **Not read** on the overview, with the error and their source on the file page.

The HTML embeds source captured during analysis and works after the original files
are changed or removed. The report therefore contains application source code;
share it with the same care as the source itself. Catalogs constructed manually
without source snapshots keep their statement listings but have no source blocks.

`html` writes a directory, so give it `--output`: printed to standard output it
is the overview page alone, without the pages it links to. The pages are written
in [doc-ui](https://k-kinzal.github.io/document-design/), document-design's
design system for documentation and reports, whose v1.0.0 stylesheet and script
are bundled unmodified under `assets/` with their license notice, so a report
looks the same offline and years on. See [the HTML report](docs/format.md#the-html-report)
for the pages and their layout.

## Alternatives, and saying what is not known

A statement assembled from a value that varies becomes one catalog entry per
alternative, at the same call site:

```
src/PostRepository.php:19  SELECT  3c4c7d386d60
  in App\PostRepository::latest via pdo.query
  SELECT id, title FROM posts ORDER BY created_at ASC
  resolved

src/PostRepository.php:19  SELECT  d2e912a436a2
  in App\PostRepository::latest via pdo.query
  SELECT id, title FROM posts ORDER BY created_at DESC
  resolved
```

Values decided together stay together, so a branch that sets both a table and a
column produces the two statements it can produce rather than the four that
pairing the values independently would suggest.

Every statement says how far the analyzer got with it:

| `resolution` | Meaning | `searchClosed` |
|--------------|---------|----------------|
| `resolved` | The text is fully determined. | yes |
| `external-input` | The values were followed to runtime input; the string is not fixed. | yes |
| `incomplete-model` | A dependency the analyzer does not model was reached. | no |
| `incomplete` | A cycle or an analysis budget stopped the search. | no |
| `not-analyzed` | The call was found but nothing was read from it. | no |

A statement whose text resolved is still not `searchClosed` when a bound on loop
passes or on callers cut the search short, since other statements may lie beyond
it, or when another candidate at the same call has an unresolved dependency.
Conditions (including `isset` and literal booleans) never select branches, so even
exact, closed and correlated candidates have no runtime reachability guarantee.
JSON format v2 states this explicitly in `analysis`. When `searchClosed` is false the statements listed may not be all of them, and
the `analysis-incomplete` or `call-not-analyzed` finding says what stopped the search. When `correlated`
is false the alternatives were paired from parts that vary independently, so some
of them may be unreachable. Stopping early is never reported as having found
nothing.

See [How the analysis works](docs/analysis.md) and
[How it is verified](docs/verification.md).

## Documentation

- [How the analysis works](docs/analysis.md)
- [How it is verified](docs/verification.md)
- [The catalog file format](docs/format.md)

## License

MIT License. See [LICENSE](LICENSE) for details.
