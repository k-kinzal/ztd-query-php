# Design

## Why sql-parser

Formatting needs the concrete SQL spelling and its syntactic structure. The
parser retains every token, comment, and explicit delimiter and does not require
a schema. Its grammar nodes distinguish an argument-list comma from a projection
separator and the AND in BETWEEN from a boolean conjunction.

`sql-semantics` serves name resolution, typing, and transformations. Its semantic
serializer generates SQL from operands; it is not the preservation boundary for
an original SQL document. Expanding its language coverage does not change that
separation of responsibilities. SQL Formatter therefore depends directly on
`sql-parser`, independently of the semantic package's development schedule.

## Pipeline

1. The caller supplies a configured MySQL, PostgreSQL, or SQLite parser.
2. The parser validates the input and produces a lossless CST.
3. For Expanded, Tabular, and River, `Syntax\Analyzer` visits grammar nodes and builds a token-indexed `Document`.
   Grammar owners mark headers, list commas, boolean operators, signs, and CASE
   expressions. `Brackets` records parenthesis pairs and nested query blocks.
4. `Layout\Renderer` applies the selected preset. `Block` establishes a local
   alignment scope; `Headers` and `Elements` emit its contents. Inline spacing is
   shared across multiline presets. `Writer` gives original comment-bearing trivia priority
   over newly selected whitespace.
5. The output is parsed again. `Fingerprint` compares the complete grammar
   derivation and original token spellings without depending on source offsets.

The signature hashes each node separately so deeply nested trees do not cause
repeated serialization escaping to grow exponentially. No parser nodes or tokens
are mutated. Every formatting call creates new layout state.

## Layout policy

Expanded places clause
bodies on the next line. Tabular and River use the same body column, with left-
and right-aligned keyword headers respectively. Lists continue at the current
body indentation. Function arguments remain inline; nested queries and CASE
expressions introduce blocks. Column definitions are laid out inside their
existing parentheses.

The multiline styles preserve case. The public options remain the four presets
and indentation width.

Comments are not text to discard. In particular, executable version-comment
markers may reside in different token trivia fields. Keeping comment-bearing
trivia intact preserves their ownership and release-dependent meaning. Likewise,
MySQL classifies some words differently depending on adjacency to `(` or `.`;
inline spacing preserves whether a gap exists at those boundaries.

The package follows the supplied parser's statement boundaries. It does not split
SQL on textual semicolons, which could occur inside strings, comments, or stored
programs. Parser errors retain their original source positions. Output verification
failures are package defects reported with `FormattingException` and the original
parse error as its cause when available.

## Compact normalization

Compact uses a separate pipeline so its reductions cannot alter the multiline
styles' preservation contract. `Compact\Trivia` first removes ordinary trivia and
records directives. Its state spans token boundaries because an active MySQL
version comment's opening marker, SQL body, and closing marker have different
owners in the lossless tree. Tokens inside executable comments retain their text
and whitespace. PostgreSQL nested comments are consumed as a whole.

`Compact\Visitor` follows the original grammar's identifier roles and optional
clauses. `Keywords` canonicalizes terminal aliases and keyword case. `Rules`
removes defaults only in recognized grammar contexts: `ALL` in a SELECT option
is different from `ALL` in a set operation or quantified comparison, for example.
SQLite keyword fallback in identifier expressions is also preserved. These rules
are dialect-specific; SQLite's `INTEGER` primary-key behavior is not reduced to
`INT`, and `CROSS JOIN` is not reduced to `JOIN`.

`Compact\Spacing` compares neighboring tokenizations under the caller's parser
and SQL mode. It keeps separators that prevent identifier, numeric, operator,
quote, or comment merging. MySQL qualification and system-variable scanner states
require context beyond a single character pair.

`Compact\Shape` removes empty and single-child grammar wrappers after the
documented reductions. Grammar owners of branching nodes remain part of the signature, so `a-(b-c)`
differs from `(a-b)-c`, and a MySQL string alias remains distinct from adjacent
string-literal concatenation. Only grammar-owned expression grouping is eligible for
removal; subqueries, row constructors, function calls, and indirection retain
their delimiters. Alias `AS` tokens are also candidates: keyword aliases that require `AS` keep it,
while valid bare aliases converge with their explicit forms. Each candidate is
rendered and reparsed before acceptance.
This costs an additional parse per grouping or alias candidate instead of relying on a
hand-maintained precedence table. State is local to each call; neither parser
trees nor tokens are mutated. Lexical separator decisions are cached within a
formatting call and reused across candidate renders.

The signature is a verification boundary for these specific reductions, not a
schema-aware semantic model. Arbitrary equivalent SQL need not share a normal
form. New equivalences require a grammar rule, exact positive and negative cases,
and preservation checks before they can participate in fuzz comparisons.

The lexical constraints follow the upstream references for
[MySQL comments](https://dev.mysql.com/doc/refman/8.4/en/comments.html),
[PostgreSQL lexical structure](https://www.postgresql.org/docs/17/sql-syntax-lexical.html),
and [SQLite expressions](https://www.sqlite.org/lang_expr.html).
