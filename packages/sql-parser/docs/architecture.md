# Architecture

The package follows the life of a parse table through five layers, and each dialect composes them.

## Grammar

`SqlParser\Grammar` holds the grammar as a set of numbered symbols, terminals first, and numbered rules, the augmented start rule first, together with the precedence declarations. `GrammarBuilder` collects what a reader finds and numbers it. A grammar remembers which generator's convention ranks an unranked rule: Bison takes the last terminal of a rule, Lemon the first ranked one.

## Compiler

`SqlParser\Compiler\Bison` reads a Bison grammar file. Host code in braces is skipped as one token, a mid-rule action becomes a hidden `$@n` nonterminal deriving the empty string and numbered before the rule it appears in, exactly as Bison numbers it, and `%prec`, `%left`, `%right`, `%nonassoc`, `%precedence`, `%start` and `%expect` are recorded.

`SqlParser\Compiler\Lemon` reads a Lemon grammar file after applying its `%if` conditionals. `%token_class` declarations and `A|B` alternations become token classes, `%fallback` and `%wildcard` are recorded for the parser, and `[PREC]` marks name a rule's precedence.

## Automaton

`SqlParser\Automaton` builds the LR(0) automaton from kernel items, computes LALR(1) lookaheads with the relations of DeRemer and Pennello, and resolves conflicts the way the generators do: a ranked shift/reduce conflict follows the ranks and the token's associativity, an unranked one shifts and is counted, and a reduce/reduce conflict goes to the earlier rule, except that Lemon lets a higher-ranked rule win. The count is compared with the grammar's `%expect`; every shipped MySQL grammar reports exactly the conflicts it declares, and the PostgreSQL and SQLite grammars report none.

A state's most common reduction becomes its default, except where Lemon's wildcard acts, and token classes are spelled out to their members before conflicts are settled.

## Table

`SqlParser\Table\ParseTable` is the runtime table. Each state has explicit actions for some symbols and a default for the rest, looked up in Lemon's order: the token, the token it falls back to, the wildcard, then the default. Tables are stored deflated under `resources/tables/`, and rows are unpacked one state at a time on first use.

## Lexer and parser

`SqlParser\Lexer` holds the cursor, tokens and exceptions the dialect lexers share. `SqlParser\Parser\LrParser` runs the shift-reduce loop over the tokens and builds the tree.

## Dialects

Each dialect has a parser front door, a version registry, a lexer ported from the server's scanner, and a build-time reader of the server's keyword table:

- MySQL: the lexer follows `sql_lex.cc` state for state, including the identifier that follows a dot, the host name that follows `@`, function names recognised only before a parenthesis, versioned comments and `WITH ROLLUP`. Keywords come from `sql/lex.h`.
- PostgreSQL: the lexer follows `scan.l`, including string continuation across newlines, the trimming of trailing `+` and `-` from operators, and the lookahead renaming `parser.c` performs for `NOT`, `NULLS`, `WITH`, `WITHOUT` and `FORMAT`. Keywords come from `kwlist.h`.
- SQLite: the lexer follows `tokenize.c`, including the contextual `WINDOW`, `OVER` and `FILTER` keywords and the semicolon supplied at the end of the text. Keywords come from `mkkeywordhash.c`, filtered by the same compile-time options the grammar is built with.

## Resources

`resources/version.php` names the shipped releases and their files. `bin/build-*.php` regenerate them from the upstream sources, keeping downloaded copies under `build/sources`.
