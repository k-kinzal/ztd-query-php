# Lemon Parser

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-lemon--parser-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/lemon-parser/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Lemon Parser reads grammar files of the [Lemon parser generator](https://sqlite.org/lemon.html) into a syntax tree that keeps everything the file says, with the position of each part. It reads the language the Lemon manual defines, so a file Lemon accepts is read as Lemon reads it and a file Lemon rejects raises an error with Lemon's message. The manual is the specification, and [spec/features](spec/features) states it clause by clause as scenarios that run the parser.

It reads grammars; it does not generate parsers. The tree is for tools that analyse, transform, document or generate from Lemon grammars.

## Requirements

- PHP 8.1 or higher

## Installation

```bash
composer require k-kinzal/lemon-parser
```

## Usage

`%ifdef` regions are settled with the names passed to `parse()`, as Lemon's `-D` option does.

```php
use LemonParser\Parser;

$file = (new Parser())->parse(file_get_contents('parse.y'), ['SQLITE_OMIT_WINDOWFUNC']);

foreach ($file->rules() as $rule) {
    echo $rule->lhs->name, ' ::=';
    foreach ($rule->items as $item) {
        echo ' ', implode('|', array_map(fn ($symbol) => $symbol->name, $item->symbols));
    }
    echo $rule->precedence === null ? '' : " [{$rule->precedence->name}]", "\n";
}
foreach ($file->declarations() as $declaration) {
    echo $declaration::class, ' at ', $declaration->location(), "\n";
}
```

A tree prints back as a grammar file, and printing is stable, which is how a tree can be checked:

```php
use LemonParser\Printer\Printer;

$text = (new Printer())->print($file);
```

Every node of the tree is described in the [API documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/lemon-parser/).

## License

MIT License. See [LICENSE](LICENSE) for details.
