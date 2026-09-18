# Fidelity to Bison

The reader follows the two files that define the grammar language in GNU Bison 3.8: `src/scan-gram.l`, the scanner, and `src/parse-gram.y`, the grammar of grammar files.

## Scanner

- Identifiers are `[.A-Za-z_]` followed by letters, digits, `-`, `_` and `.`, so `expr.opt` and `select-item` are identifiers and `1FOO` is an error.
- After an identifier the scanner looks past whitespace and comments for `[name]` and `:`, which is how a rule's left-hand side and a named reference are recognised.
- Character literals and strings decode octal, hexadecimal, universal-character and C escapes; a character literal must decode to one byte; `_("...")` is a translatable alias.
- Tags nest angle brackets, so `<std::vector<int>>` is one tag; `<*>` and `<>` are the special tags of `%destructor` and `%printer`.
- Braced code counts braces and the digraphs `<%` and `%>`, and passes over strings, character literals and comments inside it. A backslash-newline, which C splices away, may fall between the two characters of `/*`, `*/`, `//`, `<%`, `%>` and `<<` and inside a string or character literal, as `scan-gram.l` allows. `%?{ ... }` is a predicate.
- Directives are matched against Bison's table: `%pure_parser` reads as `%pure-parser`, `%term` as `%token`, `%binary` as `%nonassoc`, `%defines` as `%header`; an unknown directive is an error. `%name-prefix`, `%file-prefix` and `%output` may be followed by `=`.
- A stray `,` is whitespace, and everything after the second `%%` is the epilogue.
- `#line N "file"` at the start of a line is a token of its own and becomes a `Line` node, because Bison orders symbols by location and the directive changes locations.
- A string or character literal keeps its spelling next to its decoded value, because Bison names string tokens by their spelling.

## Grammar

- Declarations follow `prologue_declaration` and `grammar_declaration`; a `;` between declarations is accepted.
- `%token` lists are read as `token_decls`: a tag applies to the entries after it, an identifier may carry a number and a string alias. `%left` and its kin read `token_decls_for_prec`, where a string is a token of its own. `%type` reads `symbol_decls`.
- Rules follow `rules` and `rhs`: an alternative may hold symbols with `[name]`, tagged actions with `[name]`, predicates, `%empty`, `%prec`, `%dprec`, `%merge`, `%expect` and `%expect-rr`, in any order and number. A rule ends at `;`, at the next rule, or at a declaration; extra semicolons are accepted. A declaration among the rules must end with `;`.

## Verification

The tree is checked two ways. Every public example in the source runs as a test. And a corpus of real grammar files is read and compared with GNU Bison: the number of rules Bison numbers in its `.output` report, mid-rule actions included, equals the number of alternatives plus mid-rule actions in the tree for PostgreSQL 12 (2724), MySQL 5.5 (2496) and PL/pgSQL 16 (253). Bison's own examples, its `parse-gram.y`, PHP 8.3 and PostgreSQL 17 read and print back stably.

Files that need a preprocessor before Bison sees them, such as Ruby's `parse.y` with its `RUBY_TOKEN` macros or MariaDB's `sql_yacc.yy` with `%ifdef`, are not Bison grammars and are rejected as Bison rejects them.
