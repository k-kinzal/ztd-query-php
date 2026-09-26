# SQL generation algorithm

SQL Faker generates SQL from the official grammar of the selected database version: MySQL's Bison grammar (`sql_yacc.yy`), PostgreSQL's Bison grammar (`gram.y`), or SQLite's Lemon grammar (`parse.y`). A grammar describes how statements, clauses, and expressions can be assembled from smaller parts.

## Grammar derivation

A grammar contains **non-terminals**, which still need expansion, and **terminals**, which represent keywords, punctuation, identifiers, or literal tokens. Each non-terminal has one or more production alternatives.

For example, a simplified SELECT grammar can be written as:

```text
statement  → SELECT expression FROM identifier
expression → identifier | integer | expression + expression
identifier → IDENT
integer    → NUM
```

Generation starts with a non-terminal representing the desired statement or fragment. The leftmost remaining non-terminal is replaced with one of its alternatives. Repeating this operation eventually produces only terminals:

```text
statement
→ SELECT expression FROM identifier
→ SELECT expression + expression FROM identifier
→ SELECT identifier + expression FROM identifier
→ SELECT IDENT + expression FROM identifier
→ SELECT IDENT + integer FROM identifier
→ SELECT IDENT + NUM FROM identifier
→ SELECT IDENT + NUM FROM IDENT
```

Alternative selection introduces variation. Expanding an expression to another expression can produce nested operations, and expanding a list recursively can produce additional columns, rows, or statements. Selecting a fragment's rule uses the same process without first generating an enclosing statement.

## Completing recursive expansions

Recursive alternatives need a finite route to terminal symbols. Before selecting an alternative, generation considers the minimum expansions needed to finish both that alternative and the rest of the statement. Alternatives that cannot finish within the remaining expansion allowance are excluded.

When additional conditions restrict production choices, completion also considers those conditions in descendant rules and repeated occurrences. If output must contain text, completion must include a terminal that contributes text rather than only empty productions or parser markers.

Until the expansion count reaches its complexity threshold, each choice is made uniformly at random among the eligible alternatives. From the threshold on, the choice is no longer random: the eligible alternative with the shortest completion is selected, measured as the fewest terminal symbols, or as the fewest rule expansions followed by the fewest terminal symbols. This allows recursive SQL structures to terminate without cutting off an unfinished statement.

## Applying structural constraints

A context-free grammar describes the shapes of SQL, but database parsers also apply rules to relationships between parts of a statement. After derivation, dialect-specific transformations account for these structural requirements, such as expression grouping and clause combinations, before choosing the final text.

## Choosing token text

The terminal sequence still contains token classes such as IDENT and NUM. Lexical generation replaces each of them with concrete text chosen from the token's candidates, which are defined for the target dialect and version. Keywords and operators have their possible spellings as candidates. Identifiers and literals have a few representative values of their lexical domain, such as `_sqlfaker_identifier` for a MySQL identifier or `0`, `1` and `2` for a MySQL integer; any other value of the domain is used only when a generation plan supplies it.

For the SELECT example, one realization is:

```text
SELECT IDENT + NUM FROM IDENT
→ SELECT _sqlfaker_identifier + 1 FROM _sqlfaker_identifier
```

Lexical forms are selected from right to left. A candidate must be compatible with the already selected text to its right and allow the remaining text to its left to be completed. This matters because a token's spelling and its neighbors can determine whether whitespace is required or forbidden.

## Joining the text

Once lexical forms have been selected, the boundary between each pair of neighboring forms is either allowed to hold a space or required to have none. A single space is placed wherever one is allowed, and nothing where the forms must be adjacent. For example, `SELECT` and `_sqlfaker_identifier` are separated so they are not read as the single identifier `SELECT_sqlfaker_identifier`, while a prefix and its quoted literal are joined. As a result, the tokens of a generated statement are separated by single spaces except where adjacency is required, so it reads like `SELECT ( 1 , 2 )` rather than `SELECT (1, 2)`.

The resolved token text and separators are then concatenated to produce the SQL string. Selecting the spellings and boundaries before concatenation preserves the intended token structure.

## Limitations

SQL Faker's scope is generating SQL syntax. It does not generate a database state in which every resulting statement is executable.

### Schema and type consistency

Generated names and values are not coordinated with an existing schema. A query can refer to a missing table or column, apply a function to an incompatible type, or violate a database constraint. For example, generating `SELECT price FROM products` does not establish that `products.price` exists. Syntactic validity therefore does not guarantee successful execution.

### Dependencies between statements

Separate generated statements do not form a coordinated database scenario. Generating CREATE TABLE followed by INSERT does not make the INSERT use that table's columns. Likewise, generation does not establish the session state, prepared statements, permissions, or transaction history that another statement may require.

### Query results

Generation does not target a particular result set or database effect. A valid SELECT may return no rows, and an UPDATE or DELETE may affect none. SQL Faker does not ensure that a query expresses an application's intended logic or produces specified values.
