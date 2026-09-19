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
vendor/bin/sql-catalog --output catalog/ --extension laravel src/

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
src/UserRepository.php:27  SELECT  SELECT id FROM users WHERE status = :status ORDER BY name
  in App\UserRepository::findByStatus via pdo.prepare
  :status = 'active'|'banned'
```

The table name came from a class constant, the sort column from a property
default, and the bound value from the enum the parameter is typed as — so the
parameter is reported as the two strings the column can actually hold, not as
`string`.

A statement the analyzer cannot fully resolve keeps its resolved parts and marks
the rest:

```
src/Search.php:17  SELECT  SELECT id FROM users WHERE name = '{$}'
  in App\Search::run via pdo.query
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

## Extensions

An extension names the calls that reach a database. Adding support for a
framework is a matter of naming its calls; nothing else about the analysis
changes.

| Extension | Covers |
|-----------|--------|
| `pdo` | `PDO` and `PDOStatement`, including drop-in subclasses such as `ZtdPdo` |
| `mysqli` | `mysqli` and `mysqli_stmt`, in both object and procedural form |
| `doctrine` | Doctrine DBAL connections and prepared statements |
| `laravel` | Raw SQL through the `DB` facade and Illuminate connections |

`pdo` and `mysqli` are enabled by default. Query builders and Eloquent assemble
their SQL at runtime and are out of reach of a source-level analyzer; what the
framework extensions catalog is the raw SQL an application still writes by hand.

Implement `SqlCatalog\Extension\ExtensionInterface` and register it on an
`ExtensionRegistry` to recognise an API this package does not ship.

## Reporters

| Reporter | Writes | Purpose |
|----------|--------|---------|
| `json` | `catalog.json` | A deterministic document; two runs of the same source produce the same bytes, so the diff of a pull request reads as the change in the SQL an application issues |
| `html` | `index.html` | One self-contained page, with no scripts and no external assets |
| `text` | `catalog.txt` | One block per statement, for reading in a terminal |

## Accuracy

The analyzer over-approximates: it may report a statement a run never reaches,
but it does not miss one a run does reach. That property is checked rather than
claimed. See [How it is verified](docs/verification.md) for the measurements and
how they are produced.

## Documentation

- [How the analysis works](docs/analysis.md)
- [How it is verified](docs/verification.md)
- [The catalog file format](docs/format.md)

## License

MIT License. See [LICENSE](LICENSE) for details.
