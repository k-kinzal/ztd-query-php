# SQL Faker Generation Algorithm

This document specifies the generation algorithm itself.

The algorithm has two phases:

1. build a grammar snapshot from an upstream grammar source
2. generate SQL from that snapshot through supported-grammar rewriting, leftmost derivation, lexical rendering, and deterministic token joining

The generator does not post-process or sanitize already-generated SQL.
All exclusions happen before derivation, at the grammar level.

## 1. Core Model

Use the following abstract model.

- A `terminal` is a leaf symbol that renders directly to a token string.
- A `non-terminal` is a symbol that expands through a production rule.
- A `production` is an ordered list of symbols.
- A `production rule` maps one non-terminal name to an ordered list of productions.
- A `grammar` is:
  - a start symbol
  - an ordered mapping from rule name to production rule

Alternative order is part of the algorithm.
It affects random branch selection and also tie-breaking when the generator starts seeking termination.

## 2. Inputs And State

The runtime generator takes:

- a dialect: MySQL, PostgreSQL, or SQLite
- a grammar version tag, or `null` to use the dialect default
- a start rule, or `null` to use the dialect default
- a seeded pseudo-random source
- a user-visible `maxDepth`

The runtime state for one generation call is:

- the supported grammar
- the table of minimum termination lengths
- the target-depth threshold
- the derivation-step counter
- the identifier-ordinal counter

Constants:

- derivation limit: `5000`
- MySQL finite arity limit for table value constructors: `8`
- PostgreSQL finite arity limit for `SELECT`/`VALUES`/CTAS-style projection families: `8`
- SQLite finite arity limit for bounded `SELECT`/`VALUES`/set-operation families: `8`

Default start rules:

- MySQL: `simple_statement_or_begin`
- PostgreSQL: `stmtmulti`
- SQLite: `cmd`

`maxDepth` is not tree depth.
It is a threshold on derivation step count.
When `derivationSteps >= targetDepth`, the generator stops choosing randomly and starts choosing the shortest terminating alternative.

## 3. Snapshot Construction

This phase turns an upstream grammar source into a serialized grammar snapshot.
Runtime generation starts from the snapshot, not from the raw upstream grammar text.

Runtime loading uses this resolution rule:

```text
load_snapshot(dialect, requestedVersion):
  if requestedVersion is not null:
    version := requestedVersion
  else:
    version := the configured default tag for that dialect

  if no serialized snapshot exists for version:
    fail

  deserialize the stored grammar snapshot
  if deserialization does not yield a grammar:
    fail

  return the grammar snapshot
```

### 3.1 Bison-Family Sources

Use this algorithm for MySQL and PostgreSQL grammars.

Input:

- a parsed upstream grammar AST
- the upstream start symbol
- token declarations
- grammar rules

Algorithm:

```text
compile_bison_grammar(ast):
  startSymbol := upstream start symbol
  ruleTable := map rule name -> rule definition
  tokenTable := map token name -> token declaration
  rules := empty ordered map

  for each upstream rule in source order:
    productions := []

    for each alternative of that rule:
      symbols := []

      for each symbol occurrence in the alternative:
        if the occurrence is a character literal:
          append terminal(literal text)
        else if its name exists in ruleTable:
          append non_terminal(name)
        else if its name exists in tokenTable:
          append terminal(name)
        else:
          fail with "unknown symbol"

      append production(symbols) to productions

    if rules already contains this rule name:
      append productions to the existing rule
    else:
      store a new rule with those productions

  return grammar(startSymbol, rules)
```

- repeated upstream rule declarations are merged by concatenating alternatives
- symbol classification is structural, not textual
- compilation fails if any symbol cannot be classified

### 3.2 Lemon-Family Sources

Use this algorithm for SQLite grammars.

Input:

- an upstream Lemon grammar text

Algorithm:

```text
compile_lemon_grammar(source):
  remove block comments and line comments
  rules := empty ordered map
  startSymbol := unset

  terminals := extract names from:
    %token
    %left
    %right
    %nonassoc
    %fallback
    %token_class
    %wildcard

  remove directive blocks and directive lines that are not grammar rules

  parse every rule of the form:
    lhs(alias)? ::= rhs . {optional action}

  for each parsed rule in source order:
    if startSymbol is unset:
      startSymbol := lhs

    lhs is a non-terminal

    rhsSymbols := split rhs by whitespace
    for each rhs symbol:
      strip aliases, for example expr(A) -> expr
      ignore empty fragments and directive fragments
      if a token-class fragment contains alternatives separated by |:
        keep only the first branch in the production
        but register every ALL_CAPS branch as a terminal name

      if the cleaned name is ALL_CAPS:
        classify it as a terminal
      else:
        classify it as a non-terminal

    append the resulting production to rules[lhs]

  return grammar(startSymbol, rules)
```

The Lemon compiler is textual and does not use the same symbol-resolution rule as the Bison-family compiler.

## 4. Supported-Grammar Construction

The raw snapshot is not used directly for generation.
It is first transformed into a supported grammar.
Let `G0` be the raw snapshot.
Let `G1` be the result of applying a fixed dialect-specific rewrite program.
Generation always derives from `G1`.

### 4.1 Rewrite Operators

The dialect rewrite programs use the following abstract operators.

`RestrictSingletonNonTerminal(rule, allowedSet)`

- keep only alternatives of length `1`
- keep only those whose single symbol is a non-terminal in `allowedSet`

`RestrictSingletonTerminal(rule, allowedSet)`

- keep only alternatives of length `1`
- keep only those whose single symbol is a terminal in `allowedSet`

`CopyNonEmpty(source, target)`

- copy all non-empty alternatives from `source` into a new rule `target`

`Filter(rule, predicate)`

- remove alternatives for which `predicate(alternative)` is false

`Replace(rule, productions)`

- replace the entire rule with the given ordered production list

`RewriteBySignature(rule, matcher, replacement)`

- inspect each alternative as its ordered symbol-name sequence
- if it matches a designated signature, replace it with a different production
- otherwise keep it unchanged

`BuildCommaList(elementRule, arity, commaToken)`

- produce `elementRule`
- then repeat `[commaToken, elementRule]` until the list length is `arity`

`BuildParenthesizedCommaList(elementRule, arity, openToken, closeToken, commaToken)`

- wrap `BuildCommaList(...)` in the given delimiters

`BuildFiniteArityFamily(baseName, elementRule, arityLimit, wrappers...)`

- generate explicit rule families for arities `1..arityLimit`
- each generated rule has a fixed number of elements
- then define the public rule as the union of those generated rules

`Ensure(rule, fallbackProductions)`

- if the rule does not exist, create it from `fallbackProductions`
- otherwise keep the existing rule

`Permutations(items)`

- enumerate every order of the given finite list

The rewrite program is deterministic because:

- it runs in a fixed order
- each operator is purely functional over the current grammar state
- no operator depends on randomness

### 4.2 MySQL Rewrite Program

Build the supported MySQL grammar by applying the following ordered rewrite program.

1. Canonicalize identifier entry points.
   Rewrite `ident`, `label_ident`, `role_ident`, and `lvalue_ident` so each keeps only the singleton non-terminal alternative `IDENT_sys`.

2. Canonicalize `user`.
   Replace `user` with exactly:
   `TEXT_STRING_sys '@' TEXT_STRING_sys`.

3. Force `ALTER EVENT` to contain a real change.
   Create non-empty copies of:
   `ev_alter_on_schedule_completion`,
   `opt_ev_rename_to`,
   `opt_ev_status`,
   `opt_ev_comment`,
   `opt_ev_sql_stmt`.
   Then redefine `alter_event_stmt` so each production requires at least one of those clauses to be non-empty.

4. Enumerate valid `COMMIT` spellings.
   Replace `commit` with an explicit list of accepted combinations involving:
   `WORK`,
   `RELEASE`,
   `NO RELEASE`,
   `AND CHAIN`,
   `AND NO CHAIN`,
   and the allowed combinations among them.

5. Enumerate valid `ROLLBACK` spellings.
   Replace `rollback` with an explicit list of accepted combinations involving:
   `WORK`,
   `RELEASE`,
   `NO RELEASE`,
   `AND CHAIN`,
   `AND NO CHAIN`,
   and `TO [SAVEPOINT] ident`.

6. Remove unsafe `ALTER INSTANCE` branches.
   Filter `alter_instance_action` to remove any alternative that:
   - contains `ident_or_text`
   - starts with `ENABLE_SYM`
   - starts with `DISABLE_SYM`

7. Prevent `<=>` from appearing before `ALL|ANY` subqueries.
   Filter `comp_op` into a second rule that excludes `EQUAL_SYM`.
   Rewrite the `bool_pri comp_op all_or_any table_subquery` shape so it uses that filtered comparison rule.

8. Enumerate valid `START TRANSACTION` option lists.
   Replace `start_transaction_option_list` with explicit productions for:
   `WITH CONSISTENT SNAPSHOT`,
   `READ ONLY`,
   `READ WRITE`,
   and the supported two-clause combinations.

9. Split role grants from object grants.
   Introduce bounded role-list rules for `GRANT` and `REVOKE`.
   Redefine the top-level grant and revoke families so role grants, object grants, and proxy grants are separate explicit alternatives.

10. Canonicalize `CLONE`.
    Replace `clone_stmt` with exactly:
    `CLONE LOCAL DATA DIRECTORY [=] TEXT_STRING_filesystem`.

11. Bound table value constructor arity.
    Define:
    - `table_value_expr ::= signed_literal | null_as_literal`
    - for each arity `1..8`, an explicit row-value family and row-list family
    - `table_value_constructor` as the union of those fixed-arity constructors

12. Restrict `SIGNAL`/`RESIGNAL` SQLSTATE literals.
    Replace the SQLSTATE family with a small finite safe domain:
    `'45000'` and `'01000'`.

13. Split `ALTER DATABASE` into explicit safe subfamilies.
    Introduce dedicated branches such as encryption-oriented forms instead of relying on the full upstream cross-product of options.

14. Restrict `LIMIT` literals.
    Replace `limit_option` with a safe finite literal domain:
    `0`, `1`, `2`, `10`, `100`.

15. Restrict character set and collation domains.
    Replace:
    - `charset_name`
    - `old_or_new_charset_name`
    - `collation_name`
    with fixed safe representatives such as `utf8mb4`, `utf8mb4_0900_ai_ci`, and `BINARY`.

16. Split `SET` into explicit safe system-variable assignments.
    Introduce a small safe domain for:
    - option type: `GLOBAL`, `LOCAL`, `SESSION`
    - system assignments: `autocommit = boolean_numeric_option`, `sql_mode = ''`
    - the `@@` forms of those same assignments
    Filter broad assignment branches that otherwise bind arbitrary lvalue variables or arbitrary expressions.

17. Collapse replication option lists.
    Reduce replication definition lists to single-element list structure where necessary and shrink some option domains to safe boolean or finite scalar forms.

18. Split `SIGNAL ... SET` information items into finite explicit families.
    Replace broad arbitrary information-item combinations with explicit safe subsets.

19. Canonicalize `RESET`.
    Restrict the reset family to safe explicit subcommands instead of arbitrary upstream option products.

20. Canonicalize `FLUSH`.
    Reduce `flush_options_list` to a single `safe_flush_option` and define that option set explicitly.

21. Bound SRS-related identifiers and literals.
    Introduce safe numeric id rules, safe definition literals, and an explicit finite family of SRS attribute sets.
    For attribute sets that may appear in different orders, enumerate every permutation of the supported attribute subset.

22. Restrict undo tablespace, diagnostics, and explain subfamilies.
    Replace wide expression domains with finite safe representatives, for example:
    - bounded diagnostics targets
    - bounded explain format names
    - explicit undo tablespace spellings

23. Restrict resource-group CPU ranges.
    Replace CPU range forms with a tiny fixed domain such as:
    `0` and `0-1`.

24. Expand multi-factor `ALTER USER` syntax explicitly.
    Replace broad factor-based combinations with explicit productions for supported `ADD`, `MODIFY`, and `DROP` multi-factor forms.

### 4.3 PostgreSQL Rewrite Program

Build the supported PostgreSQL grammar by applying the following ordered rewrite program.

1. Canonicalize identifier-like rules.
   Restrict `ColId`, `ColLabel`, `type_function_name`, and `NonReservedWord` to the singleton terminal `IDENT`.

2. Canonicalize qualified names.
   Redefine `qualified_name` and `any_name` to the canonical family:
   - `ColId`
   - `ColId '.' attr_name`

3. Canonicalize function names.
   Redefine `func_name` to:
   - `type_function_name`
   - the canonical qualified-name family from step 2

4. Remove unsafe indirection shapes.
   Filter `indirection_el` so it excludes:
   - bracket subscripting forms beginning with `'['`
   - the `'.' '*'` form

5. Collapse permissive-policy defaults.
   Force `RowSecurityDefaultPermissive` to the empty production.

6. Split `CREATE TABLE ... PARTITION OF` into explicit safe forms.
   Remove overly broad temporary and partition-option cross-products and replace them with dedicated safe branches.

7. Split `ALTER DATABASE`.
   Replace broad option products with explicit safe branches over canonical names and bounded option families.

8. Introduce a safe temporary-relation family.
   Ensure the existence of:
   - `safe_temporary_relation_name`
   - `safe_temporary_relation_modifier`
   - `safe_table_non_temp_modifier`
   - `safe_view_non_temp_modifier`
   - `safe_sequence_non_temp_modifier`
   The temporary modifier family is the finite set:
   `TEMPORARY`,
   `TEMP`,
   `LOCAL TEMPORARY`,
   `LOCAL TEMP`,
   `GLOBAL TEMPORARY`,
   `GLOBAL TEMP`.

9. Split broad DDL families into object-specific safe grammars.
   Rewrite the shared `ALTER TABLE`-like upstream families into dedicated safe branches for:
   - tables
   - indexes
   - views
   - sequences
   - statistics
   - materialized views
   - domains
   - types
   - enums

10. Restrict role-related statements.
    Replace role creation, dropping, granting, revoking, and altering with explicit safe role-name and option-list families instead of the full upstream option lattice.

11. Restrict function and routine references.
    Introduce safe families for:
    - `function_with_argtypes`
    - alter-routine option lists
    - create-routine argument lists
    - return-type and return-body subfamilies

12. Restrict utility-statement targets.
    Replace wide object-target families with safe canonical names for:
    - event triggers
    - access methods
    - text search templates
    - extension contents
    - configuration parameters
    - large objects

13. Restrict operator and aggregate definition forms.
    Filter operator-definition and operator-argument families, then replace broad `CREATE OPERATOR` and `CREATE AGGREGATE` definitions with explicit safe subgrammars.

14. Restrict publication, comment, cast, assertion, partition, and type-reference families.
    Introduce dedicated safe families for:
    - publication object specifications
    - type references
    - cast signatures
    - assertion check expressions
    - partition strategies
    - drop-type name lists

15. Collapse `cte_list`.
    Replace `cte_list` with a single-element list grammar:
    one `common_table_expr` only.

16. Rebuild `SELECT` around bounded families.
    Apply all of the following:
    - remove `DEFAULT` from the general select-expression family
    - create a recursive `select_expr_list`
    - define `safe_select_value_expr ::= ICONST | NULL`
    - define a safe `DISTINCT ON` expression domain using only identifiers and constants
    - build explicit `VALUES` families for arities `1..8`
    - build explicit set-operation operand and statement families for arities `1..8`
    - redefine `simple_select`, `select_core`, `select_no_parens`, and `select_with_parens` to use those bounded families

17. Rebuild CTAS around bounded projection families.
    For each arity `1..8`:
    - build a fixed-size column list
    - build a fixed-size select target list
    - build a matching `SELECT`
    - build matching non-temporary and temporary CTAS targets
    Then redefine `CREATE TABLE AS` and `CREATE TABLE AS EXECUTE` using those bounded families.

18. Rebuild `CREATE VIEW` around bounded projection families.
    For each arity `1..8`:
    - build a fixed-size view column list
    - build a fixed-size view select target list
    - build a matching `SELECT`
    Then redefine non-recursive and recursive `CREATE VIEW` forms over those bounded families.

19. Rebuild `INSERT` around bounded projection families.
    For each arity `1..8`:
    - build a fixed-size insert column list
    - build a fixed-size insert target list
    - build a matching `SELECT`
    Restrict conflict-update clauses to a very small safe assignment family and separate `DO UPDATE` from `DO NOTHING`.

20. Restrict `CREATE MATERIALIZED VIEW`, `MERGE`, and `DO`.
    Replace those families with explicit safe subgrammars that avoid broad expression or clause products.

### 4.4 SQLite Rewrite Program

Build the supported SQLite grammar by applying the following ordered rewrite program.

1. Extract statement-specific entry rules from `cmd`.
   Partition `cmd` alternatives into:
   - `insert`
   - `delete`
   - `update`
   - `drop_table`
   - `alter_table`
   The grouping rule is syntactic:
   inspect the first terminal, or when the first symbol is non-terminal, inspect the second terminal.

2. Remove unsupported `DELETE ... ORDER BY` forms.
   After the partition in step 1, filter the extracted `delete` group so any alternative containing `orderby_opt` is removed.

3. Remove unsafe expression branches.
   Filter `expr` to remove:
   - any alternative containing the terminal `WITHIN`
   - any alternative beginning with `RAISE`

4. Remove underconstrained window branches.
   Filter `window` to remove alternatives that mention only `frame_opt` and `nm` non-terminals and contain no terminal keyword.

5. Remove keyword-like identifier branches.
   Filter:
   - `nmnum` to remove alternatives beginning with `ON`, `DELETE`, or `DEFAULT`
   - `nm` to remove alternatives beginning with `STRING`

6. Rebuild `CREATE TABLE`.
   Introduce:
   - `safe_dbnm ::= ε | '.' nm`
   - `create_table_head`
   - `create_table ::= create_table_head create_table_args`
   Then rewrite the corresponding `cmd` signature so it points at this wrapper rule.

7. Rebuild `ATTACH` and `DETACH`.
   Introduce:
   - `safe_attach_filename_expr ::= STRING`
   - `safe_attach_schema_expr ::= nm`
   - `attach_stmt`
   - `detach_stmt`
   Then rewrite the corresponding `cmd` signatures to use those rules.

8. Rebuild `VACUUM`.
   Introduce:
   - `safe_vacuum_into_expr ::= STRING`
   - `safe_vinto ::= ε | INTO safe_vacuum_into_expr`
   - `vacuum_stmt`
   Then rewrite the corresponding `cmd` signatures to use that safe family.

9. Rebuild temporary-object families.
   Introduce explicit wrappers for:
   - `CREATE VIEW`
   - `CREATE TEMP VIEW`
   - `CREATE TRIGGER`
   - `CREATE TEMP TRIGGER`
   and redirect the corresponding `cmd` alternatives to those wrappers.

10. Rebuild `SELECT` around bounded families.
    Apply all of the following:
    - define `safe_selcollist_no_from` by removing `*` from the select-list family
    - define `safe_from_clause ::= FROM seltablist`
    - define `safe_select_result_expr ::= expr`
    - define `safe_select_value_expr ::= term`
    - for each arity `1..8`, build fixed-size result lists, row-value lists, `VALUES` clauses, and set-operation operands/statements
    - redefine `oneselect` so no-`FROM` selects use `safe_selcollist_no_from`
    - redefine `selectnowith` so it is either a single bounded select or a bounded set-operation select

## 5. Termination-Length Analysis

After building the supported grammar, compute the minimum terminating token length of every rule.

This is a fixed-point computation.

Algorithm:

```text
ComputeTerminationLengths(grammar):
  infinity := very large integer
  length[rule] := infinity for every rule

  changed := true
  while changed:
    changed := false

    for each rule:
      best := length[rule]

      for each alternative in rule:
        altLength := 0
        valid := true

        for each symbol in alternative:
          if symbol is terminal:
            altLength += 1
          else if symbol is non-terminal:
            if length[symbol] is infinity:
              valid := false
              break
            altLength += length[symbol]

        if valid and altLength < best:
          best := altLength

      if best != length[rule]:
        length[rule] := best
        changed := true

  for each rule still equal to infinity:
    length[rule] := 1

  return length
```

Rules that remain at infinity after the fixed-point iteration are assigned length `1`.

For any production, its termination estimate is the sum of:

- `1` for each terminal
- the precomputed rule length for each non-terminal, or `1` if that rule length is unavailable

## 6. Derivation

Generation uses leftmost derivation.

Algorithm:

```text
Generate(startRule, maxDepth):
  derivationSteps := 0
  identifierOrdinal := 0
  targetDepth := max(1, maxDepth)
  start := user-provided startRule or dialect default

  form := [non_terminal(start)]

  loop:
    index := position of the first non-terminal in form
    if no non-terminal exists:
      break

    derivationSteps += 1
    if derivationSteps > 5000:
      fail with "derivation limit exceeded"

    current := form[index]

    if dialect is SQLite and current has no rule:
      replace form[index] with terminal(current.name)
      continue

    if dialect is MySQL or PostgreSQL and current has no rule:
      fail with "unknown grammar rule"

    alternatives := grammar[current.name]
    if alternatives is empty:
      fail with "production rule has no alternatives"

    if derivationSteps >= targetDepth:
      choose the alternative with minimum termination estimate
      if several alternatives tie, choose the earliest one
    else:
      choose one alternative uniformly by pseudo-random index

    splice the chosen production in place of form[index]

  return form as the final terminal sequence
```

- leftmost means the generator always expands the first remaining non-terminal, never a later one
- the generator resets `identifierOrdinal` and `derivationSteps` on every call
- the target-depth switch happens exactly at equality, not strictly after it

## 7. Lexical Sampling Primitives

Terminal rendering depends on these lexical primitives.

`CanonicalIdentifier(ordinal)`

- output `_i` followed by the base-36 encoding of `max(ordinal, 0)`
- examples: `_i0`, `_i1`, `_iz`, `_i10`

`RawIdentifier(minLength, maxLength)`

- choose a total length in `[max(minLength, 1), maxLength]`
- first character is `_`
- remaining characters come from `[a-z0-9_]`

`MixedString(minLength, maxLength)`

- choose a length uniformly in the range
- every character comes from `[a-zA-Z0-9_]`

`IntegerString(min, max)`

- choose an integer uniformly in the range and render it in decimal

`UnsignedBigIntString(minLength, maxLength)`

- choose a digit string of that length range
- trim leading zeros
- if all digits were zero, return `0`

`DecimalString(precision, scale)`

- `intDigits := max(precision - scale, 1)`
- `intPart := random integer in [0, 99...9]` using `intDigits` digits
- `fracDigits := max(scale, 2)`
- `fracPart := random integer in [0, 99...9]` using `fracDigits` digits
- output `intPart . '.' . fracPart`, left-padding the fractional part with zeros to exactly `fracDigits`

`FloatString(mantissa, minExponent, maxExponent)`

- output `mantissa + 'e' + random integer exponent`

`HexString(minLength, maxLength)`

- choose a string over `[0-9a-f]`

`BinaryString(minLength, maxLength)`

- choose a string over `[0-1]`

`HostnameString(minParts, maxParts, minPartLength, maxPartLength)`

- choose a part count in the range
- each part starts with `[a-z]`
- the rest of the part uses `[a-z0-9]`
- join parts with `.`

`ParameterIndex(min, max)`

- choose a decimal integer in the range

## 8. Terminal Rendering

Derivation returns a terminal-name sequence.
Rendering maps each terminal name to a token string.

### 8.1 MySQL Rendering

Rendering rules:

- discard `END_OF_INPUT`
- map symbolic operators:
  - `EQ -> =`
  - `EQUAL_SYM -> <=>`
  - `LT -> <`
  - `GT_SYM -> >`
  - `LE -> <=`
  - `GE -> >=`
  - `NE -> <>`
  - `SHIFT_LEFT -> <<`
  - `SHIFT_RIGHT -> >>`
  - `AND_AND_SYM -> &&`
  - `OR2_SYM`, `OR_OR_SYM -> ||`
  - `NOT2_SYM -> NOT`
  - `SET_VAR -> :=`
  - `JSON_SEPARATOR_SYM -> ->`
  - `JSON_UNQUOTED_SEPARATOR_SYM -> ->>`
  - `NEG -> -`
  - `PARAM_MARKER -> ?`

- render lexical terminals:
  - `IDENT -> CanonicalIdentifier(identifierOrdinal++)`
  - `IDENT_QUOTED -> backtick + CanonicalIdentifier(identifierOrdinal++) + backtick`
  - `TEXT_STRING -> "'" + MixedString(1, 32) + "'"`
  - `NCHAR_STRING -> 'N' + string literal`
  - `DOLLAR_QUOTED_STRING_SYM -> "$$" + MixedString(1, 32) + "$$"`
  - `NUM -> IntegerString(1, 2147483647)`
  - `LONG_NUM -> IntegerString(0, 2147483647)`
  - `ULONGLONG_NUM -> UnsignedBigIntString(1, 20)`
  - `DECIMAL_NUM -> DecimalString(10, 2)`
  - `FLOAT_NUM -> FloatString(DecimalString(10, 2), -38, 38)`
  - `HEX_NUM -> '0x' + HexString(1, 16)`
  - `BIN_NUM -> '0b' + BinaryString(1, 64)`
  - `LEX_HOSTNAME -> HostnameString(1, 1, 1, 16)`
  - `FILTER_DB_TABLE_PATTERN -> "'" + HostnameString(1,1,1,12) + "." + HostnameString(1,1,1,12) + "'"`
  - `RESET_MASTER_INDEX -> IntegerString(1, 2000000000)`

- render special keyword composites:
  - `WITH_ROLLUP_SYM -> WITH ROLLUP`

- default keyword rule:
  - if a terminal name ends with `_SYM`, strip that suffix
  - otherwise use the terminal name text unchanged

### 8.2 PostgreSQL Rendering

Rendering rules:

- discard parser-mode markers:
  - `MODE_TYPE_NAME`
  - `MODE_PLPGSQL_EXPR`
  - `MODE_PLPGSQL_ASSIGN1`
  - `MODE_PLPGSQL_ASSIGN2`
  - `MODE_PLPGSQL_ASSIGN3`

- map symbolic operators:
  - `TYPECAST -> ::`
  - `DOT_DOT -> ..`
  - `COLON_EQUALS -> :=`
  - `EQUALS_GREATER -> =>`
  - `NOT_EQUALS -> !=`
  - `LESS_EQUALS -> <=`
  - `GREATER_EQUALS -> >=`
  - `NOT_LA -> NOT`
  - `WITH_LA -> WITH`
  - `WITHOUT_LA -> WITHOUT`
  - `FORMAT_LA -> FORMAT`
  - `NULLS_LA -> NULLS`

- render lexical terminals:
  - `IDENT -> CanonicalIdentifier(identifierOrdinal++)`
  - `UIDENT -> 'U&"' + CanonicalIdentifier(identifierOrdinal++) + '"'`
  - `SCONST -> "'" + MixedString(1, 32) + "'"`
  - `DO_BODY_SCONST -> "'BEGIN NULL; END'"`
  - `USCONST -> "U&'" + MixedString(1, 24) + "'"`
  - `ICONST -> IntegerString(1, 2147483647)`
  - `FCONST -> DecimalString(10, 2)`
  - `BCONST -> "B'" + BinaryString(1, 64) + "'"`
  - `XCONST -> "X'" + HexString(1, 16) + "'"`
  - `PARAM -> '$' + ParameterIndex(1, 99)`

- render `Op` by sampling one symbol from:
  `+ - * / < > = ~ ! @ # % ^ & |`

- default keyword rule:
  - if a terminal name ends with `_P`, strip that suffix
  - otherwise use the terminal name text unchanged

### 8.3 SQLite Rendering

Rendering rules:

- lexical terminals:
  - `ID`, `id`, `idj -> SQLiteIdentifier(identifierOrdinal++)`
  - `ids -> '"' + RawIdentifier(1, 128) + '"'`
  - `STRING -> "'" + MixedString(1, 32) + "'"`
  - `number`, `INTEGER`, `QNUMBER -> IntegerString(1, host-maximum integer)`
  - `VARIABLE -> '?' + ParameterIndex(1, 10)`

- punctuation and operators:
  - `LP -> (`
  - `RP -> )`
  - `SEMI -> ;`
  - `COMMA -> ,`
  - `DOT -> .`
  - `EQ -> =`
  - `LT -> <`
  - `PLUS -> +`
  - `MINUS -> -`
  - `STAR -> *`
  - `BITAND -> &`
  - `BITNOT -> ~`
  - `CONCAT -> ||`
  - `PTR -> ->`

- keyword families sampled from finite sets:
  - `JOIN_KW -> one of {LEFT, RIGHT, INNER, CROSS, NATURAL LEFT, NATURAL INNER, NATURAL CROSS}`
  - `CTIME_KW -> one of {CURRENT_TIME, CURRENT_DATE, CURRENT_TIMESTAMP}`
  - `LIKE_KW -> one of {LIKE, GLOB}`

- special token renames:
  - `AUTOINCR -> AUTOINCREMENT`
  - `COLUMNKW -> COLUMN`

- default rule:
  - use the terminal name text unchanged

`SQLiteIdentifier(n)` works as follows:

1. compute `buf := CanonicalIdentifier(n)`
2. lowercase it
3. if it equals any reserved word in the following set, return `"buf"`:

`add, all, alter, and, as, between, by, case, check, collate, commit, create, default, delete, distinct, do, drop, else, end, escape, except, exists, for, foreign, from, group, having, if, in, index, insert, into, is, join, key, limit, match, no, not, null, of, on, or, order, primary, references, select, set, table, then, to, union, unique, update, using, values, when, where, with`

4. otherwise return `buf`

## 9. Token Joining

Token rendering produces a token list.
A second pass joins the tokens into one SQL string.

Algorithm:

```text
Join(tokens, extraNoSpacePairs):
  output := ""
  prev := null

  for token in tokens:
    if output is empty:
      append token
      prev := token
      continue

    needsSpace := true

    if token == "(" and prev looks like an identifier:
      needsSpace := false
    else if token == ")" or prev == "(" or token == "," or token == ";":
      needsSpace := false
    else if prev == "." or token == ".":
      needsSpace := false
    else if prev == "[" or token == "]":
      needsSpace := false
    else if (prev, token) matches any extra no-space pair:
      needsSpace := false

    if needsSpace:
      append " "

    append token
    prev := token

  return output
```

An identifier is:

- a bare word matching `[A-Za-z_][A-Za-z0-9_]*`
- or a matching backtick-quoted identifier
- or a matching double-quoted identifier

Dialect-specific extra no-space pairs:

- MySQL: `('@', '*')`, `('*', '@')`, `('*', ':')`, `(':', '*')`
- PostgreSQL: `('::', '*')`, `('*', '::')`
- SQLite: `('->', '*')`, `('*', '->')`

## 10. Determinism And Failure Modes

For a fixed:

- dialect
- grammar version
- start rule
- pseudo-random seed
- `maxDepth`

the generator is deterministic.

That determinism depends on:

- stable alternative order
- deterministic supported-grammar rewriting
- deterministic termination-length computation
- deterministic token-joining rules

Failure modes:

- exceeding `5000` derivation steps is a hard error
- choosing from a rule with zero alternatives is a hard error
- an unknown non-terminal is a hard error in MySQL and PostgreSQL
- an unknown non-terminal becomes a literal terminal token in SQLite

## 11. Summary

The full algorithm is:

1. build or load a grammar snapshot
2. transform it into a supported grammar through a fixed dialect rewrite program
3. precompute minimum terminating token lengths
4. perform leftmost derivation
5. switch from random choice to shortest-terminating choice once `derivationSteps >= max(1, maxDepth)`
6. render terminals through dialect-specific lexical rules
7. join tokens through deterministic spacing rules

There is no repair stage after step 7.
If a syntax family is unsupported, it must already have been removed or replaced in step 2.
