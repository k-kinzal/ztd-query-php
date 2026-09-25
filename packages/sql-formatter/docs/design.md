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
3. `Syntax\Analyzer` visits grammar nodes and builds a token-indexed `Document`.
   Grammar owners mark headers, list commas, boolean operators, signs, and CASE
   expressions. `Brackets` records parenthesis pairs and nested query blocks.
4. `Layout\Renderer` applies the selected preset. `Block` establishes a local
   alignment scope; `Headers` and `Elements` emit its contents. Inline spacing is
   shared across presets. `Writer` gives original comment-bearing trivia priority
   over newly selected whitespace.
5. The output is parsed again. `Fingerprint` compares the complete grammar
   derivation and original token spellings without depending on source offsets.

The signature hashes each node separately so deeply nested trees do not cause
repeated serialization escaping to grow exponentially. No parser nodes or tokens
are mutated. Every formatting call creates new layout state.

## Layout policy

Compact walks tokens without inserting layout line breaks. Expanded places clause
bodies on the next line. Tabular and River use the same body column, with left-
and right-aligned keyword headers respectively. Lists continue at the current
body indentation. Function arguments remain inline; nested queries and CASE
expressions introduce blocks. Column definitions are laid out inside their
existing parentheses.

All styles preserve case. Uppercasing a keyword-shaped identifier or renaming an
identifier is outside this package's initial contract. The only initial options
are the four presets and indentation width.

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
