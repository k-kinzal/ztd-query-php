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
  expression constructor. Independent source/model and output/model checking is
  not yet complete; this dispatch alone does not establish correspondence.
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
  actual input and rendered-output correspondence must be checked before
  publication. Those omissions prevent claiming G3, G5, and G6 in full.
- Implementation: `Construction\SelectSnapshot`, `Query\Select`,
  `Query\ScopedSelect`, `SubqueryConstruction`.

## SQLITE-EXPRESSION-SPELLING-001

**Status:** Partly implemented; complete lexical/output correspondence pending.

- Purpose: preserve expression-derived output names without turning an arbitrary
  name string into expression SQL. Explicit grouping and binary operator spelling
  have bounded concrete values. No value contains an operand's SQL fragment.
- Preconditions: a binary spelling encodes its actual operator, and its outer gaps
  contain only validated whitespace. Grouping contains a real semantic child.
- Composition: the renderer adds parentheses required by the child's binding
  power and the parent's associativity. Adjacent minus signs cannot accidentally
  open a comment; adjacent word characters require separation.
- Alias uses retain the actual name and field identity. They are not rendered by
  substituting, duplicating, or reordering the target expression.
- Observation: a direct column label follows the declared column name, including
  through grouping; a computed expression label follows the profile's expression
  naming rule. Parentheses and operator gaps can affect that label even when the
  value is unchanged.
- Independent evidence: SQLite in-memory comparisons check rows, storage classes,
  and result-column names. These observations find errors in the rule but are not
  a proof over all compositions.
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
