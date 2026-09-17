# Lemon Parser

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-lemon--parser-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/lemon-parser/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Lemon Parser reads grammar files of the [Lemon parser generator](https://sqlite.org/lemon.html), the one SQLite is built with, into a syntax tree that keeps everything the file says: every rule with its aliases, multi-terminal positions, precedence mark and action, and every declaration from `%token_prefix` to `%token_class`, each with its position. It follows `lemon.c` itself, so a file Lemon accepts is read as Lemon reads it and a file Lemon rejects raises an error with Lemon's message. `%ifdef` regions are settled with the names you pass, as Lemon's `-D` option does.

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

The command line does the same:

```bash
vendor/bin/lemon-parser check parse.y                         # reads the file and checks that printing it is stable
vendor/bin/lemon-parser -DSQLITE_OMIT_CTE stats parse.y       # counts declarations and rules with a name defined
vendor/bin/lemon-parser print parse.y                         # writes the file back out
vendor/bin/lemon-parser preprocess parse.y                    # writes the file after %ifdef regions are settled, as lemon -E does
```

See [docs/tree.md](docs/tree.md) for every node of the tree and [docs/fidelity.md](docs/fidelity.md) for what "as Lemon reads it" covers.

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

## Conformance with Lemon

`composer conformance -- --lemon=PATH DIR...` reads every grammar under the directories with Lemon and with this package, under each set of defines listed for it, and requires that the preprocessed text equals `lemon -E`, that a file Lemon reads prints back to a file from which Lemon produces the same report, and that a file Lemon stops reading raises a `SyntaxException` here. The CI runs it against Lemon built from SQLite 3.47.2 on SQLite's grammars and a hand-written corpus. See [docs/fidelity.md](docs/fidelity.md).

## License

MIT License. See [LICENSE](LICENSE) for details.
