# Fidelity to Lemon

The reader follows `lemon.c` as shipped with SQLite 3.47.2: `preprocess_input` and `eval_preprocessor_boolean` for the conditional regions, the tokenizer in `Parse`, and the state machine in `parseonetoken`.

## Preprocessor

- `%if`, `%ifdef`, `%ifndef`, `%else` and `%endif` count only at the start of a line, and `%if`, `%ifdef` and `%ifndef` only when a space follows the keyword, as in Lemon.
- Directive lines and excluded regions are blanked, not removed: every byte but the line break becomes a space. Lemon does the same, which keeps line numbers in its messages true, and here keeps columns true as well.
- The expression is evaluated as Lemon evaluates it: left to right with no operator precedence, `!` negating the next term, parentheses grouping, `||` answering true as soon as the left side is true and `&&` answering false as soon as it is false. A name is true when it was passed as a define.
- The output equals `lemon -E` byte for byte on SQLite's `parse.y` (3.8.0, 3.20.0, 3.35.0, 3.47.2) and `fts5parse.y` under every combination of `-D` options tried.

## Tokenizer

- Whitespace and `//` and `/* */` comments are skipped, with Lemon's quirk that `/*/` does not close the comment it opens.
- A string is anything between double quotes, with no escapes. A code block runs to the brace that balances its opening one, skipping braces inside comments, strings and character literals; a backslash escapes inside a literal.
- A word starts with a letter or digit and continues with letters, digits and underscores. `::=` is one token. `|X` and `/X`, a bar or slash followed by a letter, is one compound token. Any other byte is a token of its own, which is why `% name` with a space is accepted just as `%name` is.

## Reader

- A rule is a lower-case word, an optional `(alias)`, `::=`, then words (symbols), `|X` compounds extending the terminal before them, `(alias)` naming the position before it, and the period. Lemon's checks apply: a compound may not contain a nonterminal, an alias must start with a letter, and any other token is `Illegal character on RHS of rule`.
- A `[TOKEN]` mark or `{ code }` after the period attaches to the rule most recently completed, even if declarations were read in between, and a second one of either is an error. `{NEVER-REDUCE}` sets the flag instead of the code.
- Declarations follow Lemon's keyword table: the one-argument keywords take braced code, a string or a word; `%left`, `%right`, `%nonassoc`, `%fallback`, `%token`, `%wildcard` and `%token_class` take terminals up to a period; `%destructor` and `%type` take a symbol and then an argument. An unknown keyword is an error.
- The symbol table checks Lemon makes while reading are made too: a second precedence for a terminal, a second `%type` for a symbol, a second fallback for a token, a second wildcard, and a `%token_class` named after a symbol already seen are errors.
- Rule counts match Lemon's `-s` statistics on every corpus grammar and define set tried: `fts5parse.y` 28, `parse.y` 3.8.0 327, 3.20.0 329, 3.35.0 398, 3.47.2 409, and the reduced counts under `-DSQLITE_OMIT_...`.

## Where this reader is stricter

Lemon reads a file to the end and simply stops; a rule cut off before its period, or a `%left` list cut off before its period, leaves no trace and no message. This reader reports both as `SyntaxException`, because a tree that silently lacks the last rule is not a tree of the file. Lemon also lets an unclosed `(` in a `%if` expression pass; this reader reports it. Nothing Lemon rejects is accepted.
