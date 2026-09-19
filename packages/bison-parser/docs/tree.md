# The tree

`Parser::parse()` returns a `GrammarFile`. Every node carries a `Location` (line and column, counted from one) of its first character.

## GrammarFile

| Property | Holds |
|----------|-------|
| `declarations` | `list<Declaration>`: everything before the first `%%`, in file order |
| `grammar` | `list<Rule\|Declaration>`: everything between the two `%%`, in file order; Bison lets grammar declarations appear among the rules |
| `epilogue` | `?Epilogue`: the text after the second `%%` |

`rules()` answers the rules of the grammar section and `allDeclarations()` the declarations of both sections.

A `#line` directive is kept where it stands as a `Line` node (`line`, `file`), among the declarations, among the rules, or inside a rule as a right-hand-side item. Bison reads it as a change of location, which changes the order it numbers symbols in, so a tree without it would not print back to the same grammar. A `#line` between two entries of one `%token` list is the one place it is passed over.

## Declarations

All implement `Declaration` and answer `location()`.

| Class | Directive | Properties |
|-------|-----------|------------|
| `Prologue` | `%{ ... %}` | `code` |
| `Flag` | `%debug`, `%locations`, `%glr-parser`, `%pure-parser`, `%yacc`, `%verbose`, `%token-table`, `%no-lines`, `%nondeterministic-parser`, `%error-verbose`, `%default-prec`, `%no-default-prec`, `%fixed-output-files` | `name` (canonical), `raw` (as written) |
| `Option` | `%file-prefix`, `%language`, `%name-prefix`, `%output`, `%require`, `%skeleton`, `%header` / `%defines` | `name`, `raw`, `value` (null when omitted) |
| `Define` | `%define variable value` | `variable`, `value`, `form` (`DefineForm::Keyword`, `String` or `Code`; null when no value) |
| `Expect` | `%expect N`, `%expect-rr N` | `count`, `reduceReduce` |
| `InitialAction` | `%initial-action {...}` | `code` |
| `Param` | `%param`, `%lex-param`, `%parse-param` | `kind` (`ParamKind`), `codes` |
| `UnionDeclaration` | `%union [name] {...}` | `name`, `code` |
| `Start` | `%start sym...` | `symbols` |
| `CodeProps` | `%destructor {...} targets`, `%printer {...} targets` | `printer`, `code`, `targets` (`Symbol` or `Tag`; `<*>` is `Tag::ANY`, `<>` is `Tag::NONE`) |
| `Code` | `%code [qualifier] {...}` | `qualifier`, `code` |
| `SymbolDeclaration` | `%token`, `%term`, `%nterm`, `%type` | `class` (`SymbolClass`), `entries` |
| `PrecedenceDeclaration` | `%left`, `%right`, `%nonassoc`, `%binary`, `%precedence` | `associativity` (`Associativity`), `entries` |

A `SymbolEntry` holds the `symbol`, the `tag` in force where it was written, the token `number` and the `alias` (`Alias` with `text` and `translatable`). After `%token` a string is the alias of the identifier before it; after `%left` and `%type` a string is a symbol of its own.

## Rules

| Class | Holds |
|-------|-------|
| `Rule` | `name` (`Symbol`), `namedReference`, `alternatives` |
| `Alternative` | `items` (`list<RhsItem>`), and `symbols()` for the symbols alone |
| `SymbolItem` | `symbol`, `namedReference` |
| `Action` | `tag`, `code`, `namedReference`; an action followed by more items is where Bison would see a mid-rule action |
| `Predicate` | `code` of `%?{ ... }` |
| `EmptyItem` | `%empty` |
| `PrecItem` | `symbol` of `%prec` |
| `DprecItem` | `value` of `%dprec` |
| `MergeItem` | `tag` of `%merge` |
| `ExpectItem` | `count`, `reduceReduce` of `%expect` / `%expect-rr` inside a rule |

## Symbols

A `Symbol` has a `kind` (`SymbolKind::Identifier`, `CharLiteral` or `String`), a `value` and, for a literal, its `spelling`. The value of a character literal is the decoded byte and the value of a string is the decoded text, so `'\n'` carries a newline; the spelling is the literal as written, quotes and escapes included. Bison tells string tokens apart by their spelling, `"\'"` and `"'"` being two tokens, so `Printer` writes a literal back as it was spelled and only encodes a symbol built without a spelling. An `Alias` keeps its `spelling` the same way.

## Errors

`SyntaxException` carries a `location` and a message that names what was expected and what was found, or what Bison rejects: an invalid directive, an invalid character, an empty or overlong character literal, a null character or an escape above one byte in a literal, an integer out of range, an unterminated string, comment, tag or code block, a symbol of the wrong kind in a declaration, `%empty` on a non-empty rule, or a rules section without rules.
