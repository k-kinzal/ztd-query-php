# Initial construction rules

These records describe the current read-only migration. They do not certify
complete SQL coverage or all of G1–G7. Status names are independent of test results.
The language profile pins the grammar artifacts and `SQLSEM-DESIGN-001/1.0` rule
revision. The records below currently apply to the SQLite 3.47.2 profile.

## NEW-SCALAR-INPUT-001

**Status:** Implemented; contract review incomplete.

- Inputs: immutable literal values, decoded column-use names, and the concrete
  classes enumerated by `Construction\InputDomain`.
- Composition condition: each scalar child belongs to that closed domain; query,
  branch, and list children have their own final concrete types. Implementing
  `ScalarInput` externally confers no membership.
- Environment: inputs have no old scope, resolution, output field, or declaration
  pointer. Derivation supplies their one actual use environment.
- Construction boundary: scalar-containing input constructors reject foreign
  implementations, before a successfully constructed input can retain them.
  Derivation independently dispatches exact final classes and rejects a foreign
  implementation if called directly.
- Derived facts: delegated to each resulting expression's semantic rule. Current
  arithmetic precision and eager fact completion still require further work.
- Correspondence: every operand slot is supplied explicitly to its concrete
  expression constructor. NEW-QUERY-CORRESPONDENCE-001 checks the actual stored operands independently
  of this dispatch. Source/model and output/model checking remain incomplete.
- Termination: each recursive derivation consumes a strict child of the finite
  input DAG. Immutable constructors cannot form input cycles through normal PHP
  construction; reflection-based private-boundary attacks are outside the model.
- Primitive assumptions: PHP readonly/final behavior, canonical literal
  validation, and the registry's accurate enumeration.
- Implementation: `Construction\InputDomain`, `ExpressionConstruction`,
  `ColumnConstruction`, and the expression/conditional/subquery input classes.

## NEW-SELECT-001

**Status:** Implemented; output-contract obligation incomplete.

- Inputs: one exact `Catalog` snapshot and a complete `SelectDefinition` containing
  the full ordered projection, named inputs, optional predicate, quantifier, and
  optional row restriction. `Fields`, bound expressions, and old query roots are
  not construction inputs.
- Composition condition: scalar inputs satisfy NEW-SCALAR-INPUT-001. The currently
  implemented constructor requires the SQLite profile. This restriction is a
  documented constructor domain, not an exclusion from SQL analysis coverage.
- Environment: each named input creates a new `TableReference`. Projection
  expressions resolve against those occurrences; predicate expressions also see
  the query's declared aliases. Count/offset expressions get the independent
  restriction scope. A context is never inferred from a prior statement.
- Declaration identity: resolved references point to the actual declarations in
  the supplied catalog. Reusing an input definition constructs new occurrences
  and use sites, while retaining canonical declaration and literal objects.
- SQL diagnostics: missing or ambiguous references remain concrete semantic
  outcomes. They are not converted to an invalid-construction error.
- Public boundary: root `Select` derives its snapshot before assigning the final
  public properties. `ScopedSelect` represents a body at an explicit lexical
  environment and does not implement `Operation`.
- Termination: finite ordered input sequences and strict expression/query descent;
  no edit history, graph transplant, incremental invalidation, or rebind pass.
- Remaining assumptions/obligations: catalog completeness and name policies need
  their full profile-specific rules; all derived facts must be completed eagerly;
  original-SQL and rendered-output correspondence must be checked before
  publication. Typed new-input correspondence is checked as recorded below. Those omissions prevent claiming G3, G5, and G6 in full.
- Implementation: `Construction\SelectSnapshot`, `Query\Select`,
  `Query\ScopedSelect`, `SubqueryConstruction`.

## NEW-VALUES-001

**Status:** Implemented; output-contract obligation incomplete.

- Inputs: one catalog snapshot and a nonempty ordered `RowsDefinition`. Each row
  supplies explicit scalar inputs; bound `Row` objects are not accepted.
- Environment: a new empty input scope is created once per query. Each row and
  scalar use is derived separately, including repeated instances of an input
  definition. Different widths remain represented for SQL diagnostics.
- Public boundary: `Rows` is an independent operation; `ScopedRows` is a body with
  an explicit lexical parent and cannot be supplied as an INSERT source root.
  Scalar-subquery, existence, and membership rules accept the scoped body through
  `SqliteSubquery`, which checks its immediate lexical parent.
- Composition and termination: NEW-SCALAR-INPUT-001 applies at every tuple position;
  the row traversal is finite and nested derivation consumes strict input children.
- Remaining obligations: independent input and output correspondence, eager facts,
  complete diagnostic precision, and all query grammar productions. This record
  does not certify complete VALUES support in SQL analysis or G5/G6.
- Implementation: `Construction\RowsSnapshot`, `RowsConstruction`, `Query\Rows`,
  `Query\ScopedRows`, and SQLite's typed query-input reader.

## NEW-QUERY-CORRESPONDENCE-001

**Status:** Implemented for the current SELECT/VALUES input domain; SQL-source and
rendered-output correspondence remain incomplete.

- Input: a complete typed new definition, the requested context or lexical parent,
  and the actual candidate snapshot. The checker also accepts a finished concrete
  query for independent inspection; it does not create or transform that query.
- Operation and slot correspondence: query form, quantifier, ordered named inputs,
  projection positions, aliases, predicate presence and operands, count/offset,
  tuple positions and widths, and every supported scalar child are checked against
  the actual stored properties. CASE base/test/result roles are not interchangeable.
  Empty membership still checks its syntactically requested subject operand.
- Environments: the exact catalog snapshot, input occurrences, declaration objects,
  and output fields are compared by identity. A freshly reconstructed alias search
  environment is admissible only around the same scope, projection, and ordered
  visible aliases. An unrelated context with equal contents is not interchangeable.
- Leaf contract: new construction retains each supplied closed immutable literal
  value itself. Name uses instead create fresh lookup sites and retain the supplied
  decoded name and quote interpretation. Resolution outcomes, including conflicts
  and conditional outer searches, are checked without selecting an alternative.
- Enforcement: `SelectSnapshot` and `RowsSnapshot` invoke the checker after actual
  properties are derived and before normal constructor return. A mismatch is an
  `InvariantViolation`, regardless of PHP's assertion configuration. No candidate
  or callback escapes while construction is incomplete.
- Trust boundary: the checker shares the fixed name-resolution primitives with
  construction. It detects misassembly and wrong actual references; it does not
  independently establish the primitives' agreement with SQLite. Concrete value
  constructors, field-name derivation, and catalog rules remain trusted rules
  requiring their own review. The checker never uses a fingerprint or SQL string
  to stand in for operand or declaration correspondence.
- Termination: scalar operand traversal uses an explicit work stack. Nested query
  checks consume strict query-definition children. Conditional resolution checks
  follow finite alternatives and strictly enclosing scopes. Deep-query construction
  and resolution still need the broader explicit-stack performance work.
- Adversarial evidence: tests reject lost predicates and tuples, reordered fields,
  changed operators and CASE evaluation forms, missing choices, wrong alias slots,
  self-join occurrence substitutions, unrelated contexts, and foreign lexical
  parents. These checks run with `zend.assertions=-1` as well as ordinary settings.
- Remaining obligations: original SQL to typed input and actual emitted SQL to
  model are separate contracts; this checker alone does not establish either one.
- Implementation: `Validation\Correspondence` and the two snapshot constructors.

## SQLITE-EXPRESSION-SPELLING-001

**Status:** Partly implemented; complete lexical/output correspondence pending.

- Purpose: preserve expression-derived output names without turning an arbitrary
  name string into expression SQL. Explicit grouping, unary and binary operator spelling,
  and CASE delimiters have bounded concrete values. No value contains an operand's SQL fragment.
- Preconditions: a binary spelling encodes its actual operator, and its outer gaps
  contain only validated, complete trivia. Grouping contains a real semantic child.
- Composition: the renderer adds parentheses required by the child's binding
  power and the parent's associativity. Adjacent minus signs cannot accidentally
  open a comment; adjacent word characters require separation. Unary NOT keeps its
  lower binding power, while sign and bitwise operators require grouping around
  weaker operands even when compact spelling was requested.
- Alias uses retain the actual name and field identity. They are not rendered by
  substituting, duplicating, or reordering the target expression.
- Observation: a direct column label follows the declared column name, including
  through grouping; a computed expression label follows the profile's expression
  naming rule. Parentheses and operator gaps can affect that label even when the
  value is unchanged.
- CASE composition: each WHEN/THEN pair retains its two actual operands and bounded
  keyword/trivia values. The optional base is written once; the optional ELSE is
  written only when present. Adjacent keyword/operand word characters receive a
  separator. These values never retain a base, predicate, result, or complete SQL
  expression. Lowercase and comment-bearing CASE output labels therefore do not
  require a newly introduced alias when their children preserve their labels.
- Independent evidence: SQLite in-memory comparisons check rows, storage classes,
  and result-column names. These observations find errors in the rule but are not
  a proof over all compositions. Capture regressions include a computed CASE label
  matching a later explicit alias: WHERE must still resolve to that later field,
  and the original and emitted statements must both return the same empty result.
- Remaining obligations: all other expression forms, comments important to output
  names, conditional output names, complete alias-capture checks, and actual
  emitted-SQL correspondence. Legacy synthetic aliases still require migration;
  successful selected examples do not discharge these obligations.
- Sources: [SQLite expressions](https://sqlite.org/lang_expr.html),
  [SQLite result-column names](https://sqlite.org/c3ref/column_name.html),
  [SQLite identifier/string compatibility](https://sqlite.org/quirks.html).
- Implementation: `Expression\Rendering`, `Construction\Rendering`,
  `Projection\Field`, `Projection\AliasReference`.

The fingerprint used by existing tests deliberately ignores grouping presentation
and operator layout after recording field labels. It remains a supplementary
comparison. It cannot validate an input slot, prove declaration identity against
an external context, or substitute for the missing correspondence checker.
