# SQL Catalog

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--catalog-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-catalog/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Catalog reads PHP source and reports every statement the code can send to a database: the statement text, the tables it names, the values bound to its placeholders, and where in the source it is issued. Nothing runs, and no database is needed. A statement assembled from a value the analyzer cannot follow is reported as the shape it has, with the gap marked and traced back to where the value came from.

## Requirements

- PHP 8.1+ with the json extension

## Getting Started

```console
$ composer require --dev k-kinzal/sql-catalog
$ vendor/bin/sql-catalog --help
sql-catalog — catalog the SQL statements a PHP application issues

Usage:
  sql-catalog [options] <path>...

Output:
  -o, --output=DIR       Write the report into DIR instead of standard output
  -r, --reporter=NAME    Render with NAME (default: text on standard output, json into a directory)

What to read:
  -c, --config=FILE      Read catalog settings (default: .catalog.yaml)
  -e, --extension=NAME   Recognise the database calls of NAME; repeatable (default: pdo,mysqli)
      --dialect=NAME     Framework builder grammar: mysql, pgsql or sqlite
      --exclude=PATTERN  Skip source files whose reported path matches; repeatable
      --root=DIR         Report paths relative to DIR (default: the working directory)

What to keep:
      --namespace=NS     Keep statements issued under a namespace; repeatable
      --method=NAME      Keep statements issued in a function, as name or Class::method; repeatable
      --path=PATTERN     Keep statements from files matching a pattern; repeatable
      --kind=KIND        Keep statements of a kind, such as select or insert; repeatable
      --table=NAME       Keep statements naming a table; repeatable
      --sink=ID          Keep statements found at a database call, such as pdo.prepare; repeatable
      --severity=LEVEL   Keep statements reported at LEVEL or above

Exit status:
      --fail-on=LEVEL    Exit with 1 when a statement is reported at LEVEL or above

Information:
      --list-extensions  List the extensions this build recognises
      --list-reporters   List the reporters this build can render with
  -h, --help             Show this help

Values given to a repeatable option may also be written separated by commas.
```

## License

MIT License. See [LICENSE](LICENSE) for details.
