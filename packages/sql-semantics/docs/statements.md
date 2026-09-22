# Statements and serialization

A Statement is an immutable snapshot of a SQL operation and its bound meaning.
Use [Binder](binder.md) to obtain it from SQL, or `StatementFactory` to construct one
from structure against a [Schema](schema.md). Both paths establish the same grammar,
name, type, and representation checks.

## Immutable transformations

Each transformation returns a new Statement of the same concrete type and operation.
The original object and all its existing fields remain unchanged. The complete result
is validated against the statement's schema snapshot before it is returned. Output
positions, name bindings, types, NULL provenance, write destinations, and nested query
facts are recomputed together. Invalid changes raise a structural, syntax, or semantic
exception; they do not publish a partially updated object.

| Receiver | Method | Result |
|----------|--------|--------|
| Every `BoundStatement` | `replaceExpression(Expression $target, Expression $replacement): static` | Replaces an owned expression by structured input, protecting operator precedence and respecting configuration-value grammar. |
| Every `BoundStatement` | `replaceStructure(Sql\Tree $target, Sql\Tree $replacement): static` | Replaces an owned grammar component and validates the complete result, including language-specific clauses. |
| `BoundSelect` | `withOutputs(array $outputs): self` | Replaces ordered `OutputColumn` projections and recomputes their result facts. |
| `BoundSelect` | `withFrom(TableUse\|Join\|null $from): self` | Replaces the input relation, including joins and derived queries; output bindings and NULL provenance follow the new input. |
| `BoundSelect`, `UpdateStatement`, `DeleteStatement` | `withWhere(?Expression $where): self` | Sets or removes the row predicate in its own scope. |
| `BoundSelect` | `withGroupBy(array $expressions): self`, `withHaving(?Expression $having): self` | Changes grouping and the group predicate. |
| `BoundQuery` | `withOrderBy(array $orderBy): static` | Replaces ordered `Ordering` keys, direction, and NULL placement. |
| `InsertStatement`, `ValuesStatement` | `withRows(array $rows): self` | Replaces ordered rows and binds their values and positional destinations. |
| `InsertStatement` | `withReturning(array $outputs): self` | Replaces RETURNING projections where provided by the selected database grammar. |
| `UpdateStatement` | `withAssignments(array $writes): self` | Replaces ordered `Assignment` objects and validates their destination columns and values. |
| `CompoundStatement` | `withBranch(int $ordinal, BoundQuery $branch): self` | Replaces a set operand and recomputes output width and common types. |
| `TableStatement` | `withTable(array $name): self` | Resolves a qualified relation name and derives its ordered outputs. |
| `MergeStatement` | `withCondition(Expression $condition): self` | Changes the match predicate and refreshes the conditional write plan. |
| `ConfigurationStatement` | `withValues(Setting $setting, array $values): self` | Replaces one owned SET value list, retaining its name and scope. |
| `CreateTableStatement` | `withColumn(ColumnDefinition $target, ColumnDefinition $replacement): self` | Replaces a declared column from another declaration and rebinds defaults, generated values, and constraints. |
| `CreateIndexStatement` | `withKey(int $ordinal, Expression $expression): self` | Replaces one key expression while retaining its modifiers. |
| `CommandStatement` | `withStatement(BoundStatement $target, BoundStatement $replacement): self` | Replaces an owned nested command while retaining the enclosing command options. |

Expression and structure targets are identified by object identity within the current
statement. An equivalent-looking object from a different parse is a different target.
Replacement values are bound in the destination scope. List setters preserve list order;
INSERT destinations and input rows must agree under the dialect's insertion rules, and
set operands must have compatible widths and types. Nested queries retain their visible
CTEs and correlation namespace for transformations.

## Construct expressions and statements

`new StatementFactory(Schema $schema)` uses the same snapshot contract as Binder.
`select(array $outputs, ?TableDefinition $from = null, ?Expression $where = null): BoundSelect`
constructs a projection without original SQL. `create(Sql\Tree $structure): BoundStatement`
accepts a complete SQL structure for any statement in the selected grammar. The factory
validates the structure and derives all semantic facts; callers do not need to invent
scope IDs, column bindings, or inferred result types.

| Construction value | Meaning |
|--------------------|---------|
| `Expression::literal(string\|int\|float\|bool\|null $value, Dialect $dialect)` | One scalar value, quoted for its database language. Numeric values must be finite. |
| `Expression::reference(array $name, Dialect $dialect)` | Unquoted identifier parts; quoting is applied to each part and binding resolves the name in its destination. |
| `Expression::binary(string $operator, Expression $left, Expression $right)` | An operator with explicit operand boundaries; its operands must use the same dialect. |
| `new OutputColumn(int $ordinal, ?string $name, Expression $expression)` | A positional output; aliases are quoted as identifiers during construction. |
| `new Sql\Tree(string $role, array $children)` | Ordered immutable productions and terminals. Empty optional productions retain an insertion position. |
| `new Sql\Atom(string $kind, string $text)` | One grammar terminal or meaningful annotation. This is SQL syntax, not an escaping API for values. |

Use literal and reference constructors for application data. `Atom` is the lower-level
syntax representation for callers constructing grammar components. Factory and Statement
validation check the resulting SQL; they do not turn arbitrary SQL syntax into a data
value. The bound model constructor's scope identities and derived metadata are maintained
by binding; use the factory and transformation methods to construct application statements.

## Serializer contract

`SqlSemantics\Serializer` is an interface with
`serialize(SqlSemantics\Model\BoundStatement $statement): string`. Implement it to
provide another formatting policy. The complete statement structure is available in
`$statement->sql`; `Tree::atoms()` supplies terminals in serialization order. Semantic
fields describe their meaning, while the complete structure retains every grammar component.

`SqlSemantics\SimpleSerializer` provides the default compact layout.
`BoundStatement::toString()` uses the same layout. Serialization reads the owned structure,
not the original SQL text. It works for parsed statements, transformed statements,
factory-created statements, and statements carrying semantic diagnostics.

Ordinary comments, source whitespace, and source positions do not participate in the SQL
structure. SimpleSerializer separates tokens as needed, keeps punctuation compact, and
adds no layout line breaks. Literal and quoted-identifier contents remain intact, including
significant embedded newlines and PostgreSQL string continuations. Meaningful optimizer
hints and inactive version comments are retained; active MySQL version-comment bodies are
already interpreted SQL for the selected release. Repeated serialization is stable.

The parser `source` remains available for diagnostics and original-text inspection.
After a transformation it describes the newly validated statement. A consumer that needs
a different layout supplies its own Serializer rather than modifying retained trivia.
