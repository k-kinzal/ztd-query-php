# Statements and serialization

A Statement is an immutable description of one SQL operation. Its concrete class
owns the operands required by that form; the [statement forms](statement-forms.md)
catalogue lists the class and structure of each SQL form. Use [Binder](binder.md) to
obtain it from SQL, or `StatementFactory` to validate constructed semantic objects
against a [Schema](schema.md).

## Immutable transformations

Transformations return a new Statement of the same concrete form. Constructors
check structural invariants; the complete changed operation is then bound against
its original schema snapshot. Name bindings, output types, NULL facts, and write
destinations are refreshed before the new object is returned. Failure leaves the
original snapshot intact.

| Receiver | Method | Result |
|----------|--------|--------|
| `BoundStatement` | `replaceExpression(Expression $target, Expression $replacement): static` | Replaces an owned expression and validates the entire operation. |
| `BoundSelect` | `withOutputs(array $outputs): self` | Replaces the ordered projections. |
| `BoundSelect` | `withFrom(TableUse\|Join\|null $from): self` | Replaces the input relation and rebinds dependent references. |
| SELECT, UPDATE, DELETE forms | `withWhere(?Expression $where)` | Sets or removes the row predicate. |
| `BoundSelect` | `withGroupBy(array $expressions)`, `withHaving(?Expression $having)` | Replaces grouping or the group predicate. |
| `BoundQuery` | `withOrderBy(array $orderBy): static` | Replaces ordered keys, directions, and NULL placement. |
| `InsertValuesStatement`, `ValuesStatement` | `withRows(array $rows)` | Replaces equal-width input rows. Other insertion forms have no row-list setter. |
| `InsertStatement` | `withReturning(array $outputs): static` | Replaces RETURNING projections. |
| `UpdateStatement` | `withAssignments(array $writes)` | Replaces ordered, typed assignments. |
| `CompoundStatement` | `withLeft(BoundQuery $left)`, `withRight(BoundQuery $right)` | Replaces one set operand and rechecks result width and types. |
| `TableStatement` | `withTable(TableDefinition $table)` | Replaces the source declaration and derives its result columns. |
| `MergeStatement` | `withCondition(Expression $condition)` | Replaces the required match predicate. |
| `SetNextTransactionStatement`, `SetDefaultTransactionStatement` | `withIsolation(?Isolation $isolation)`, `withAccess(?Access $access)` | At least one characteristic must remain present. Default changes additionally own `withScope(DefaultScope $scope)`. |
| `SetCurrentTransactionStatement`, `SetSessionTransactionStatement` | `withModes(array $modes)`, `withLocality(Locality $locality)` | Requires a nonempty ordered list of isolation, access, or deferrability enums; the target transaction scope stays fixed by the class. |
| `SetTransactionSnapshotStatement` | `withSnapshot(Literal $snapshot)`, `withLocality(Locality $locality)` | Requires a PostgreSQL text literal identifying the requested snapshot. |
| Password request statements | `withAccount(AccountName\|CurrentAccount $account)` | Changes the target reference while retaining the credential source form. |
| `SetPasswordStatement`, `SetDerivedPasswordStatement` | `withPassword(Literal $password)` | Requires a MySQL text literal; hashing or authentication is not performed. |
| `SetPasswordHashStatement` | `withHash(Literal $hash)` | Retains the distinct already-encoded credential form. |
| `SetPasswordStatement`, `SetRandomPasswordStatement` | `withCurrentPassword(?Literal $currentPassword)`, `withRetainCurrentPassword(bool $retainCurrentPassword)` | Changes the verification operand or secondary-password request, validated against the selected release. |
| `SetDerivedPasswordStatement` | `withDerivation(PasswordDerivation $derivation)` | Selects the MySQL 5.6 derivation request. |
| `SetAccountOptionsStatement` | `withOperations(array $operations)` | Requires at least two ordered clauses, including a credential change; each variable clause has one assignment. |
| `SetStatement` | `withValues(AssignedSetting $setting, array $values)` | Replaces an owned setting's nonempty value list. |
| `CreateTableStatement` | `withColumn(ColumnDefinition $target, ColumnDefinition $replacement)` | Replaces an owned declaration and rebinds defaults, generation expressions, and constraints. |
| `CreateIndexStatement` | `withKey(int $ordinal, Expression $expression)` | Replaces a key while retaining its index modifiers. |

Targets are identified by object identity. A similar-looking expression from another
parse is a different target. Replacements are interpreted in the destination scope,
including visible common table expressions and correlated outer references.

## Construction

`StatementFactory::select(array $outputs, ?TableDefinition $from = null,
?Expression $where = null): BoundSelect` constructs a SELECT without source SQL.
`StatementFactory::create(BoundStatement $statement): BoundStatement` validates a
native semantic object against the factory's schema while preserving its concrete
form. It does not accept an unclassified syntax tree.

| Construction value | Meaning |
|--------------------|---------|
| `Expression::literal(string\|int\|float\|bool\|null $value, Dialect $dialect)` | A scalar SQL literal with dialect-appropriate quoting. |
| `Expression::reference(array $name, Dialect $dialect)` | Identifier parts to resolve in the destination scope. |
| `Expression::binary(string $operator, Expression $left, Expression $right)` | A classified binary operator with two required operands. |
| `OutputColumn(int $ordinal, ?string $name, Expression $expression)` | One ordered result position and its optional alias. |

CTE definitions accept a query or a PostgreSQL data-modifying operation. Alias lists
are checked against known result widths; WITH definitions retain their declaration
order and require distinct names under the dialect's identifier rules. A CTE cannot
contain an unrelated session or maintenance command.

Operation classes carry native typed operands; enum choices and constructor checks
prevent incompatible shapes. INSERT VALUES requires rows; INSERT SELECT requires a
query. A write slot may instead use `DefaultSource::Column`; a scalar query slot
requires an expression. Row and set-operation widths must agree when known.
IN candidates must match their searched value's known row width. A row constructor
requires two or more fields outside PostgreSQL. Empty projections are PostgreSQL
SELECT forms, and empty IN candidate lists are SQLite forms; immutable changes
enforce these dialect-specific cardinalities.
`ValuesStatement`, `TableStatement`, and `CompoundStatement` derive their result
columns from their operands during construction; result columns cannot be supplied
as an independent, contradictory argument. Supplied columns, computed
columns, and identity columns have different value-source types. A diagnostic source
node is provenance, not an escape hatch for unclassified semantics.

## Serializer contract

`Serializer` defines `serialize(BoundStatement $statement): string`. A custom
serializer visits the concrete Statement, relation, expression, and declaration
objects to provide a formatting policy. It receives semantic operands, not a generic
SQL tree stored on every statement.

`SimpleSerializer` implements the compact layout. Output is derived from semantic
fields, so replacements and constructed objects determine the generated SQL. The
original parser `source` is not used as a fallback for an unknown operation.

`BoundStatement::toString()` returns SQL text according to the statement's origin:

- A Statement returned by `Binder::bind()` or `Binder::bindAll()` writes back exactly
  the SQL text it was bound from, including comments, whitespace, and keyword case:
  `$binder->bind($sql)->toString() === $sql`. Each statement from `bindAll()` writes
  its own segment of the script, so the segments concatenate to the original text.
  Replacing diagnostics or the transformation context keeps this text.
- A Statement produced by a `with...()` transformation or `replaceExpression()`, or
  constructed from operands through `StatementFactory`, has no original text. Its
  formatting information is discarded and `toString()` writes it from its semantic
  operands with `SimpleSerializer`.

Call `SimpleSerializer::serialize()` directly to obtain the compact layout of a bound
Statement. Every Statement satisfies its invariants in both cases.

Ordinary comments, source whitespace, and source positions do not affect the
serialized layout. Literal and quoted-identifier contents remain intact, including
significant embedded newlines. Classified optimizer directives remain meaningful
operands. Active MySQL version-comment bodies have already been parsed for the
selected release; inactive comments do not contribute an operation.

Repeated serialization is stable. After a transformation, `source` describes the
newly validated SQL, while the original Statement retains its original provenance.

XA control statements own `withTransactionId()`. Start, end, and commit forms also
own `withMode()` with their respective policy enums. `XaRecoverStatement` owns
`withEncoding()`; its result columns derive from that request. These methods return
a new validated statement and retain the original snapshot. A transaction format
requires a branch qualifier, each identifier component must be a MySQL byte-string
literal within its length domain, and policies cannot be assigned to unrelated XA
operations.
