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

Early choices are random among eligible alternatives. Once the expansion count reaches its complexity threshold, selection favors shorter completions: fewer terminal symbols, or fewer rule expansions followed by fewer terminal symbols. This allows recursive SQL structures to terminate without cutting off an unfinished statement.

## Applying structural constraints

A context-free grammar describes the shapes of SQL, but database parsers also apply rules to relationships between parts of a statement. After derivation, dialect-specific transformations account for these structural requirements, such as expression grouping and clause combinations, before choosing the final text.

## Choosing token text

The terminal sequence still contains token classes such as IDENT and NUM. Lexical generation replaces them with concrete text, selecting identifiers, literal values, keyword spellings, and operators for the target dialect and version.

For the SELECT example, one realization is:

```text
SELECT IDENT + NUM FROM IDENT
→ SELECT price + 1 FROM products
```

Lexical forms are selected from right to left. A candidate must be compatible with the already selected text to its right and allow the remaining text to its left to be completed. This matters because a token's spelling and its neighbors can determine whether whitespace is required or forbidden.

## Joining the text

Once lexical forms have been selected, their boundary requirements determine the separators. For example, `SELECT` and `price` need separation so they are not read as the single identifier `SELECTprice`. Other forms require adjacent characters, such as a prefix and its quoted literal.

The resolved token text and separators are then concatenated to produce the SQL string. Selecting the spellings and boundaries before concatenation preserves the intended token structure.

## Limitations

SQL Faker's scope is generating SQL syntax. It does not generate a database state in which every resulting statement is executable.

### Schema and type consistency

Generated names and values are not coordinated with an existing schema. A query can refer to a missing table or column, apply a function to an incompatible type, or violate a database constraint. For example, generating `SELECT price FROM products` does not establish that `products.price` exists. Syntactic validity therefore does not guarantee successful execution.

### Dependencies between statements

Separate generated statements do not form a coordinated database scenario. Generating CREATE TABLE followed by INSERT does not make the INSERT use that table's columns. Likewise, generation does not establish the session state, prepared statements, permissions, or transaction history that another statement may require.

### Query results

Generation does not target a particular result set or database effect. A valid SELECT may return no rows, and an UPDATE or DELETE may affect none. SQL Faker does not ensure that a query expresses an application's intended logic or produces specified values.
