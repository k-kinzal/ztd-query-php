# Lemon Parser

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-lemon--parser-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/lemon-parser/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Lemon Parser reads grammar files of the [Lemon parser generator](https://sqlite.org/lemon.html), the one SQLite is built with, into a syntax tree that keeps everything the file says: every rule with its aliases, multi-terminal positions, precedence mark and action, and every declaration from `%token_prefix` to `%token_class`, each with its position. It reads the language the Lemon manual defines, so a file Lemon accepts is read as Lemon reads it and a file Lemon rejects raises an error with Lemon's message. The manual is the specification, and [spec/features](spec/features) states it clause by clause as scenarios that run the parser. `%ifdef` regions are settled with the names you pass, as Lemon's `-D` option does.

It reads grammars; it does not generate parsers. The tree is for tools that analyse, transform, document or generate from Lemon grammars.

## Requirements

- PHP 8.1 or higher

## Installation

```bash
composer require k-kinzal/lemon-parser
```

## Usage

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

Every node of the tree is described in the [API documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/lemon-parser/), and what "as Lemon reads it" covers is stated by the specification below.

## What is kept

| Written in the file | In the tree |
|---------------------|-------------|
| `expr(A) ::= expr(B) PLUS\|MINUS expr(C). [STAR] { A = B + C; }` | `Rule` with `RhsItem`s, the precedence mark and the `CodeBlock` |
| `{NEVER-REDUCE}` after a rule | `Rule::$neverReduce` |
| `%name`, `%include`, `%code`, `%token_prefix`, `%token_type`, `%extra_argument`, `%syntax_error`, `%stack_size`, `%start_symbol` and the other one-argument keywords | `Directive` with the keyword, the argument and whether it was braced code, a string or a word |
| `%left`, `%right`, `%nonassoc` | `PrecedenceDeclaration`, in file order so levels can be counted |
| `%destructor`, `%type` | `Destructor`, `TypeDeclaration` |
| `%fallback`, `%token`, `%wildcard`, `%token_class` | `Fallback`, `TokenDeclaration`, `Wildcard`, `TokenClass` |
| `%ifdef` / `%ifndef` / `%if` / `%else` / `%endif` | settled before reading; blanked lines keep their line breaks, so positions still point into the original file |

Host code is kept as written and never interpreted.

## Specification

The language is specified in [spec/features](spec/features): one feature per section of "The Lemon Parser Generator" (doc/lemon.html of SQLite 3.47.2) that defines the grammar-file language, one scenario per clause. Every feature opens with its source, and every scenario is tagged with the section it states (`@manual:4.2`). Where the manual is silent, `tool/lemon.c` of the same release is read as the reference, never run: those scenarios are tagged with the function they follow (`@source:parseonetoken`), cite its line, and state Lemon's error message. The three places where this reader is stricter than Lemon, a rule or a list cut off by the end of the file and an unclosed parenthesis in a `%if` expression, stand in their own feature (`@reader`). Every scenario gives a grammar file and, when needed, the names defined for its `%ifdef` regions, parses it with `Parser`, and states either the whole tree or the position and message of the error.

```bash
composer spec                                   # the whole specification
vendor/bin/behat --tags=@manual:4.4.8           # one section of the manual
vendor/bin/behat --tags=@source:Parse           # the clauses taken from one function of lemon.c
```

The specification covers the language of grammar files. What Lemon checks on the grammar the file describes, such as an alias unused in its action, conflicts, or a start symbol that has no rule, is not part of reading the file and is out of scope.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Source traceability

[Requirements definitions](requirements/README.md) trace selected sections of the
SQLite 3.47.2 Lemon manual to Behat scenarios and keep this reader's independent
strictness decisions explicit. Run `php ../requirements/bin/requirements coverage`,
`spec` or `spec --no-test --without-source` from this directory after installing
`../requirements` in the monorepo.
