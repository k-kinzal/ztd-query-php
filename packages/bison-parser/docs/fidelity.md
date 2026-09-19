# Fidelity to Bison

The reader follows the GNU Bison 3.8.2 manual, whose clauses run as the scenarios in `spec/features` (`composer spec`), and where the manual is silent, Bison's own test suite. Each feature names its source, and each scenario is tagged with the manual node (`@manual:Symbols`) or the test file (`@tests:input.at`) it states. The notes below name the points where reading the manual takes a decision.

## Scanner

- Identifiers are `[.A-Za-z_]` followed by letters, digits, `-`, `_` and `.`, so `expr.opt` and `select-item` are identifiers and `1FOO` is an error.
- After an identifier the scanner looks past whitespace and comments for `[name]` and `:`, which is how a rule's left-hand side and a named reference are recognised.
- Character literals and strings decode octal, hexadecimal, universal-character and C escapes; a character literal must decode to one byte; `_("...")` is a translatable alias. The null character is rejected however it is escaped (`\0`, `\x0`, `\u0000`, `\U00000000`), as the manual forbids it, and so is any escape above one byte.
- A token number is a nonnegative decimal or hexadecimal integer; above 2147483647 it is out of range, as in Bison.
- Tags nest angle brackets, so `<std::vector<int>>` is one tag; `<*>` and `<>` are the special tags of `%destructor` and `%printer`.
- Braced code counts braces and the digraphs `<%` and `%>`, and passes over strings, character literals and comments inside it. Only a `}` that brings the count back to zero ends the code; a `%>` is counted but never ends it, as the manual says of the top level. A backslash-newline, which C splices away, may fall between the two characters of `/*`, `*/`, `//`, `<%`, `%>` and `<<` and inside a string or character literal, as `scan-gram.l` allows. `%?{ ... }` is a predicate.
- Directives are matched against Bison's table: `%pure_parser` reads as `%pure-parser`, and so does every deprecated spelling with underscores for dashes that Bison's test suite lists (`%no_default-prec`, `%fixed_output-files`, ...); `%term` reads as `%token`, `%binary` as `%nonassoc`, `%defines` as `%header`; an unknown directive is an error. `%name-prefix`, `%file-prefix` and `%output` may be followed by `=`.
- A stray `,` is whitespace, and everything after the second `%%` is the epilogue.
- `#line N "file"` at the start of a line is a token of its own and becomes a `Line` node, because Bison orders symbols by location and the directive changes locations.
- A string or character literal keeps its spelling next to its decoded value, because Bison names string tokens by their spelling.

## Grammar

- Declarations follow `prologue_declaration` and `grammar_declaration`; a `;` between declarations is accepted.
- Symbol lists follow the syntax the manual gives under "Syntax of Symbol Declarations". A `%token` entry starts with an identifier or a character literal and may carry a number and a string alias; a string cannot start one. `%nterm` takes identifiers only. `%type` takes identifiers, characters and strings without numbers. In `%left` and its kin a string is a token of its own, and it may carry a number as the manual's example `%left OR 134 "<=" 135` shows; Bison 3.8.2's own `parse-gram.y` does not accept a number after a string there, and the manual was followed.
- The rules section must hold at least one rule, and `%empty` on an alternative that has a symbol, or an action that another item follows, is an error, as the manual states.
- Rules follow `rules` and `rhs`: an alternative may hold symbols with `[name]`, tagged actions with `[name]`, predicates, `%empty`, `%prec`, `%dprec`, `%merge`, `%expect` and `%expect-rr`, in any order and number. A rule ends at `;`, at the next rule, or at a declaration; extra semicolons are accepted. A declaration among the rules must end with `;`.

## Verification

The tree is checked two ways. Every clause of the manual runs as a scenario in `spec/features`, which parses a grammar file and states the whole tree or the position of the error. And every public example in the source runs as a test.

What Bison checks on the grammar the file describes, such as undeclared symbols, redeclarations, duplicate `%define` variables, unused rules or conflicts, is not part of reading the file and is out of scope.

Files that need a preprocessor before Bison sees them, such as Ruby's `parse.y` with its `RUBY_TOKEN` macros or MariaDB's `sql_yacc.yy` with `%ifdef`, are not Bison grammars and are rejected as Bison rejects them.
