# The tree

`Parser::parse()` returns a `GrammarFile`. Every node carries a `Location` (line and column, counted from one) of its first character. Since the preprocessor blanks rather than removes lines, a location in the tree is a location in the file as written.

## GrammarFile

| Property | Holds |
|----------|-------|
| `items` | `list<Rule\|Declaration>`: everything in the file, in order |

`rules()` and `declarations()` answer each kind alone, in order. The order matters for `%left` and its kin, which rank tokens by the order of the declarations.

## Rule

| Property | Holds |
|----------|-------|
| `lhs` | `Symbol`: the nonterminal being defined |
| `lhsAlias` | the name in `lhs(name)`, or null |
| `items` | `list<RhsItem>`: the right-hand side, possibly empty |
| `precedence` | `Symbol` of a `[TOKEN]` mark after the period, or null |
| `code` | `CodeBlock` of the `{ ... }` action after the period, or null |
| `neverReduce` | true when `{NEVER-REDUCE}` follows the rule |

`symbols()` lists every symbol of the right-hand side, the terminals of a shared position included.

An `RhsItem` holds `symbols` (one `Symbol`, or the terminals of `A|B|C` that share the position) and the `alias` written in parentheses after it. `isMultiTerminal()` tells the two apart.

## Symbol

A `Symbol` is a `name` at a `location`. As in Lemon, a name starting with an upper-case letter is a terminal (`isTerminal()`) and one starting with a lower-case letter is a nonterminal (`isNonterminal()`).

## Declarations

All implement `Declaration` and answer `location()`, the position of the `%` sign.

| Class | Written as | Properties |
|-------|------------|------------|
| `Directive` | `%name X`, `%include {...}`, `%code {...}`, `%token_prefix TK_`, `%token_type {...}`, `%default_type {...}`, `%extra_argument {...}`, `%extra_context {...}`, `%syntax_error {...}`, `%parse_accept {...}`, `%parse_failure {...}`, `%stack_overflow {...}`, `%stack_size 100`, `%start_symbol s`, `%realloc f`, `%free f`, `%token_destructor {...}`, `%default_destructor {...}` | `keyword` (`DirectiveKeyword`), `value` without delimiters, `form` (`ArgumentForm::Code`, `String` or `Word`) |
| `Destructor` | `%destructor sym {...}` | `symbol`, `value`, `form` |
| `TypeDeclaration` | `%type sym {...}` | `symbol`, `value`, `form` |
| `PrecedenceDeclaration` | `%left A B.`, `%right A.`, `%nonassoc A.` | `associativity` (`Associativity`), `symbols` |
| `Fallback` | `%fallback ID A B.` | `symbols`; `fallback()` is the first, `tokens()` the rest |
| `TokenDeclaration` | `%token A B.` | `symbols` |
| `Wildcard` | `%wildcard ANY.` | `symbol`, or null for `%wildcard.` |
| `TokenClass` | `%token_class id ID\|INDEXED.` | `name`, `tokens` |

A `Directive`'s `form` records whether the argument was written as `{code}`, `"string"` or a bare word, so the printer writes it back the same way; Lemon strips only the delimiters and treats the three alike.

## Errors

`SyntaxException` carries a `location` and Lemon's own wording: `Illegal character on RHS of rule: "?".`, `Unknown declaration keyword: "%foo".`, `Cannot form a compound containing a non-terminal`, `The precedence symbol must be a terminal.`, `Extra wildcard to token: X`, and so on. Two conditions Lemon lets pass silently are reported as errors here: a rule or list left unterminated by the end of the file. See [fidelity.md](fidelity.md).
