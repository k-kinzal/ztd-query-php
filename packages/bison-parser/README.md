# Bison Parser

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-bison--parser-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/bison-parser/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Bison Parser reads GNU Bison grammar files (`.y`, `.yy`) into a syntax tree that keeps everything the file says: every declaration, every rule and alternative, actions and predicates as text, type tags, token numbers and aliases, named references, precedence modifiers, and the position of each of them. It reads the language the GNU Bison manual defines, so a file Bison accepts is read as Bison reads it and a file Bison rejects raises an error at the same place. The manual is the specification, and [spec/features](spec/features) states it clause by clause as scenarios that run the parser.

It reads grammars; it does not generate parsers. The tree is for tools that analyse, transform, document or generate from Bison grammars.

## Requirements

- PHP 8.1 or higher

## Installation

```bash
composer require k-kinzal/bison-parser
```

## Usage

```php
use BisonParser\Parser;

$file = (new Parser())->parse(file_get_contents('grammar.y'));

foreach ($file->declarations as $declaration) {
    echo $declaration::class, ' at ', $declaration->location(), "\n";
}
foreach ($file->rules() as $rule) {
    echo $rule->name->value, ': ', count($rule->alternatives), " alternatives\n";
    foreach ($rule->alternatives as $alternative) {
        foreach ($alternative->symbols() as $symbol) {
            echo '  ', $symbol->kind->value, ' ', $symbol->value, "\n";
        }
    }
}
```

A tree prints back as a grammar file, and printing is stable, which is how a tree can be checked:

```php
use BisonParser\Printer\Printer;

$text = (new Printer())->print($file);
```

The command line does the same:

```bash
vendor/bin/bison-parser check grammar.y     # reads the file and checks that printing it is stable
vendor/bin/bison-parser stats grammar.y     # counts declarations, rules and alternatives
vendor/bin/bison-parser print grammar.y     # writes the file back out
```

See [docs/tree.md](docs/tree.md) for every node of the tree and [docs/fidelity.md](docs/fidelity.md) for what "as Bison reads it" covers.

## What is kept

| Written in the file | In the tree |
|---------------------|-------------|
| `%{ ... %}` | `Prologue` with the code |
| `%token`, `%nterm`, `%type` with `<tag>`, numbers and `"aliases"` | `SymbolDeclaration` with one `SymbolEntry` per symbol |
| `%left`, `%right`, `%nonassoc`, `%precedence` | `PrecedenceDeclaration` |
| `%define`, `%code`, `%union`, `%param`, `%destructor`, `%printer`, `%start`, `%expect`, `%initial-action`, and every flag and option | one declaration class each |
| `name[ref]: sym[ref] <tag>{ action }[ref] %?{ predicate } %prec X %dprec 2 %merge <f> %empty ;` | `Rule` with `Alternative`s of `RhsItem`s, in order |
| the text after the second `%%` | `Epilogue` |
| `#line N "file"` | `Line`, where it stands |

Host code is kept as written and never interpreted. Literals keep their spelling next to their decoded value, since Bison names string tokens by their spelling. Deprecated spellings such as `%pure_parser`, `%term` and `%binary` are read as Bison reads them.

## Specification

The language is specified in [spec/features](spec/features): one feature per section of the GNU Bison 3.8.2 manual that defines the grammar-file language (chapter "Bison Grammar Files", the GLR and precedence sections that add to it, and the appendix "Bison Symbols"), one scenario per clause. Where the manual is silent, Bison's own test suite fixes the behaviour, and those scenarios name the test they follow. Every scenario gives a grammar file, parses it with `Parser`, and states either the whole tree or the position of the error, so the scenarios are both the reading of the manual and the check that the parser follows it.

```bash
composer spec
```

The specification covers the language of grammar files. What Bison checks on the grammar the file describes, such as undeclared symbols, redeclarations, unused rules or conflicts, is not part of reading the file and is out of scope.

## License

MIT License. See [LICENSE](LICENSE) for details.
