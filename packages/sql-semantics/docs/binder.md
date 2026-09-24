# Binder

`Binder` describes a SQL operation against an immutable [Schema](schema.md). It
resolves references and derives types, NULL facts, and dependencies. The result
contains the information a consumer needs to evaluate the operation. Binding does
not obtain runtime values, execute functions, modify rows, or apply session changes.

## Public interface

```php
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)',
);
$statement = (new Binder($schema))->bind(
    'SELECT id, score + 1 AS next_score FROM users WHERE score > 0',
);

if ($statement instanceof BoundSelect) {
    $statement->outputs[0]->expression->lineage()[0]->column->name; // id
    $statement->outputs[1]->name;                                 // next_score
    $statement->outputs[1]->expression->type->name;               // integer
    $statement->outputs[1]->expression->nullability->value;       // not-null
    $statement->where?->inputs()[0]->lineage()[0]->column->name;   // score
}
```

`bind(string $sql, bool $strict = true): BoundStatement` reads one statement.
`bindAll(string $sql, bool $strict = true): array` returns an ordered list. Each
statement uses the same supplied schema snapshot. The statements do not form an
executed script: a SET or CREATE in that list does not change the context of later
statements. Supply a new schema snapshot to interpret changed state.

## Statement forms

The concrete class returned by `bind()` specifies which operands exist. `kind` is a
`StatementKind` enum derived from that class. Classes with different required inputs
have different constructors; unrelated operands are not represented by empty or
nullable fields. Query classes are in `Model` or `Model\Statement`; the other forms
are in subnamespaces of `Model\Statement` named after their area.

The [statement forms catalogue](statement-forms.md) lists every SQL form with the
class `bind()` returns and the structure that class carries, grouped by area:

- [Queries](statement-forms.md#queries)
- [Data modification](statement-forms.md#data-modification)
- [Configuration and session](statement-forms.md#configuration-and-session)
- [Transactions](statement-forms.md#transactions)
- [Accounts, roles and privileges](statement-forms.md#accounts-roles-and-privileges)
- [Schema definition — tables, indexes, views](statement-forms.md#schema-definition--tables-indexes-views)
- [Schema definition — other objects](statement-forms.md#schema-definition--other-objects)
- [Stored programs](statement-forms.md#stored-programs)
- [Server inspection (SHOW)](statement-forms.md#server-inspection-show)
- [Replication and server administration](statement-forms.md#replication-and-server-administration)
- [Maintenance and loading](statement-forms.md#maintenance-and-loading)

### Destinations and conditional writes

An `Insertion` maps each input position to a `Storage\Path`. `ColumnPath` requires a
column reference. `FieldPath`, `ElementPath`, and `SlicePath` add their own required
field or index operands. An unresolved destination remains a classified unresolved
column reference, accompanied by diagnostics.

`ScalarAssignment` has one path and one expression. `DefaultAssignment` has a
required destination and requests its declared default without an expression.
`TupleRowAssignment` requires an `InputRow`: ordered slots containing an expression
or `DefaultSource::Column`. `TupleQueryAssignment` requires a query with compatible
width. Input order is retained, including repeated assignments.

`InsertValuesStatement::rows` contains the same expression-or-default slots.
`DefaultSource` is a storage instruction, outside the expression hierarchy. It cannot
be passed as an arithmetic operand, a projection, or a predicate. `ValuesStatement`
is a query and accepts expression rows only.

A conflict selector is `AnyConflict`, `IndexConflict` with required ordered keys,
or `ConstraintConflict` with a required constraint name. `DoNothing` has no write
payload. `DoUpdate` requires ordered assignments and may have its own predicate.

MERGE actions distinguish updates, deletes, no action, default insertion, and
single-row insertion. `MergeRowInsertion` requires exactly one `InputRow`. Its `MatchKind` identifies the rows visible to that
branch; insertion requires a missing target, while updates require an existing one.

## Relations and query stages

Each `TableUse` has an occurrence `id`, owning `scopeId`, declaration, and optional
alias. Self joins retain separate identities. `BoundSelect::relations` is derived
from its complete `from` input; callers cannot supply a contradictory relation list.

| Input form | Structured result |
|------------|-------------------|
| Named table | `TableReference` with a qualified name and declaration. PostgreSQL includes descendant tables. |
| PostgreSQL `ONLY table` | `OnlyTableReference` with the same identity information and an explicit restriction to this table's rows. This type also identifies UPDATE, DELETE, TABLE, LOCK, and CREATE INDEX targets. |
| Derived SELECT | `DerivedRelation` with a required query and LATERAL policy. |
| Common table expression | `CteReference` with its required definition. A statement's `ctes` owns ordered `CommonTableExpression` definitions, column aliases, and materialization policy. |
| `JOIN ... ON ...` | `OnJoin` with a required predicate and two inputs. |
| `JOIN ... USING(id)` | `UsingJoin` with a nonempty set of `SharedColumn` pairs and their merged output expressions. |
| `NATURAL JOIN` | `NaturalJoin` retaining its shared columns, including the case of no shared names. |
| `CROSS JOIN` | `CrossJoin`, with no predicate field. |
| Parenthesized, aliased join | `AliasedRelation` with a required inner input and its own visible output names. |
| Table function | `FunctionRelation` with a required invocation and output declaration. |
| `JSON_TABLE(...)` | `DocumentRelation` whose `JsonTable` retains the input document, row path, PASSING variables, and nested column declarations. Value, existence, ordinality, and nested-path columns have different types. |
| `XMLTABLE(...)` | `DocumentRelation` whose `XmlTable` retains namespaces, document and row-path expressions, passing modes, and typed value or ordinality columns. |

An unqualified `*` expands the complete joined row in the selected dialect's
column order. PostgreSQL places USING columns in list order, followed by the
remaining left and right columns. MySQL orders shared columns by the first input
(the right input for RIGHT JOIN), then the remaining inputs. SQLite retains the
left input's column positions and omits matching right columns. A qualified `t.*`
expands that table occurrence. Nested joins retain earlier shared columns and
separate columns with duplicate names; an ambiguous unqualified reference reports
`ambiguous-column`. Outer joins add NULL-extension facts to the nullable input,
including expressions produced by an earlier join.

Document-table output columns derive from their declarations. A value column retains
its own PATH, type, and default/error behavior. Nested JSON paths retain their
parent-child structure. Those declarations are available to a consumer; binding
does not read or expand the document.

Ordering distinguishes an input expression from an output alias or output position.
A `NamedRowLock` requires explicit relation targets; `AllRowLock` applies to eligible
inputs in that query. Lock strength and waiting behavior are enums. Nested queries
own their own predicates, ordering, pagination, windows, and locks.

## Selected values

Operations that produce results implement `ResultStatement`. Commands such as SET,
COMMIT, and REINDEX do not expose a result-column API.
`ResultStatement::resultColumns()` returns ordered `OutputColumn` objects. Each has
an `ordinal`, optional output `name`, and typed `expression`. Repeated names retain
separate positions. Wildcards expand using visible declarations. An unresolved
wildcard records an unknown result width rather than pretending to be one known
column. PostgreSQL permits a projection with zero output columns. MySQL and SQLite
SELECT structures require at least one output.

| Expression form | Information returned |
|-----------------|----------------------|
| Column | `ColumnReference` with a `ColumnBinding`: relation identity, table identity, and column symbol containing its declared type and NULL fact. |
| Literal | `LiteralKind` and exact SQL literal `text`, preserving numeric precision and quoting. |
| PostgreSQL typed literal | An explicit `CastExpression` retains the literal operand and the declared type identity, including precision, interval fields, or a user-defined type name and modifiers. Binding does not parse the literal into a runtime date or other value. |
| Date/time extraction | `Extract` has a required `PostgreSqlField` or `MySqlUnit` enum and a required temporal `value`. The field must match the operand dialect. Its result is PostgreSQL numeric or MySQL bigint; field names are normalized without evaluating the input. |
| MySQL temporal arithmetic | `DateShift` has required temporal `value`, interval `quantity`, `MySqlUnit`, and `ShiftDirection`. `IntervalOperandOrder` retains input order, including leading intervals, so anonymous parameter positions survive serialization. DATE_ADD, DATE_SUB, ADDDATE, SUBDATE, and infix interval forms bind these roles. |
| String position | `Position` has required `needle` and `haystack` expressions in the same dialect. Its integer result and NULL facts are derived from those operands; binding does not perform the search. |
| Binary or unary operation | An operator enum and required `left`/`right` or `operand`. MySQL and PostgreSQL truth tests (`IS TRUE`, `IS FALSE`, `IS UNKNOWN`, and their negations) are unary enum cases with a non-NULL predicate result. |
| Function | `FunctionCall` has a registered or unresolved function reference and ordered value arguments. |
| PostgreSQL COALESCE and NULLIF | `Coalesce` retains ordered alternatives; `NullIf` has required `left` and `right` comparison operands. These are language operations. |
| PostgreSQL GREATEST and LEAST | `Extremum` has an `ExtremumKind selection` and nonempty ordered `arguments` converted to the common result type. |
| MySQL and SQLite conditional functions | COALESCE and NULLIF are `FunctionCall` objects referencing their registered `FunctionSignature`, including application replacements. |
| Aggregate over values | `AggregateCall` retains value arguments, ALL/DISTINCT, optional input ordering, and FILTER. |
| Aggregate over rows, such as `count(*)` | `AllRowsAggregate` retains the function reference and optional FILTER; it has no value-argument list. |
| Ordered-set aggregate | `OrderedSetCall` separates `directArguments` from the required `withinGroup` row ordering and optional FILTER. Both argument groups participate in signature resolution. |
| Window function | `WindowCall` retains the invocation and its window specification or named window reference. |
| JSON membership | MySQL `JsonMembership` has a required searched `value` and JSON `array` input. Neither is evaluated during binding. |
| SQL/JSON functions | `JsonScalarExtraction` (JSON_VALUE) retains the document, path, RETURNING type and ON EMPTY/ON ERROR responses, plus PostgreSQL's document FORMAT and PASSING variables. PostgreSQL `JsonQueryExtraction` (JSON_QUERY) adds the wrapper and quotes, `JsonExistence` (JSON_EXISTS) its ON ERROR response; `JsonSerialization`, `JsonParse` and `JsonScalarConversion` retain JSON_SERIALIZE, JSON() and JSON_SCALAR. `JsonObjectConstructor`, `JsonArrayConstructor`, `JsonArrayQuery`, `JsonObjectAggregate` and `JsonArrayAggregate` retain members or elements, NULL handling, key uniqueness, ordering, FILTER and RETURNING. Result types derive from RETURNING or the function's default; options PostgreSQL rejects raise `InvalidSql`. |
| PostgreSQL named infix operator, `a OPERATOR(schema.op) b` | `QualifiedInfixOperation` retains a `QualifiedOperator` (schema path and symbol) and required `left`/`right`. No operator catalog is consulted, so the result type is unknown. A built-in written through `pg_catalog` or without a schema, such as `OPERATOR(pg_catalog.+)` or `~~`, binds as its plain operator instead; a bare symbol the binder does not classify, such as `#`, binds as an unqualified `QualifiedOperator`. More than a database and a schema before the symbol raises `InvalidSql`. |
| PostgreSQL named prefix operator, `OPERATOR(schema.op) a` | `QualifiedPrefixOperation` retains the `QualifiedOperator` and the required `operand`, with the same normalization of built-ins and an unknown result type. |
| PostgreSQL quantified comparison with a named or pattern operator | `ArrayComparison` and `QuantifiedComparison` accept a `QualifiedOperator` as well as a comparison operator, and a possibly negated LIKE or ILIKE (also written `~~`, `!~~`, `~~*`, `!~~*`). The result is boolean; a built-in operator that never yields boolean, such as `OPERATOR(pg_catalog.+)`, raises `InvalidSql`. |
| PostgreSQL `value IS [NOT] DOCUMENT` | `DocumentPredicate` retains the tested `value` and `negated`. The boolean result is NULL for a NULL value. |
| PostgreSQL XMLEXISTS | `XmlExistence` retains the XPath `path`, the `document`, and the BY REF or BY VALUE `PassingMode` written before and after the document. |
| PostgreSQL XMLPARSE | `XmlParse` retains the DOCUMENT or CONTENT `XmlOption`, the parsed `value`, and whether PRESERVE WHITESPACE was requested. The result is xml. |
| PostgreSQL XMLSERIALIZE | `XmlSerialization` retains the `XmlOption`, the `value`, the `target` type, which is also the result type, and INDENT. A target that is not a character string type raises `InvalidSql`. |
| PostgreSQL XMLROOT | `XmlRoot` retains the `value`, the `version` expression or null for VERSION NO VALUE, and the `XmlStandalone` request. |
| PostgreSQL XMLELEMENT | `XmlElement` retains the element `name`, the XMLATTRIBUTES list as `XmlNamedArgument` items, and the content values. A repeated attribute name, or an unnamed attribute that is not a column reference, raises `InvalidSql`. |
| PostgreSQL XMLFOREST | `XmlForest` retains a nonempty list of `XmlNamedArgument` elements, each named by its alias or by the referenced column; an unnamed expression raises `InvalidSql`. |
| PostgreSQL XMLPI | `XmlProcessingInstruction` retains the `target` name and the optional `content`. |
| PostgreSQL XMLCONCAT | `XmlConcatenation` retains the nonempty ordered `values`; the xml result is NULL only when every value is. |
| PostgreSQL XMLAGG | An `AggregateCall` to the registered `xmlagg` aggregate, with an xml result, input ordering, and FILTER. |
| CASE | `SimpleCase` requires a selector; `SearchedCase` requires predicates. Each retains ordered branches and its optional ELSE value. |
| Row constructor | `RowExpression` retains ordered field `items`. PostgreSQL allows zero or more fields; MySQL and SQLite require at least two. |
| IN with value candidates | `InList` has a searched `value`, ordered `choices`, and `negated` flag. Known row widths must agree; SQLite permits an empty candidate list. |
| Scalar subquery | `ScalarSubquery` with a query producing one known column, or an unresolved width. |
| Row subquery | `RowSubquery` with a row-producing query, distinct from a scalar query. |
| EXISTS, IN, quantified comparison | Dedicated classes retain the query and each required comparison operand. |
| Variable | `VariableReference` identifies the supplied variable definition and scope. A missing definition produces `UnresolvedVariableReference`. Neither contains the current runtime value. |
| Context value | `ContextReference` identifies operations such as CURRENT_DATE; it does not retrieve the current date. |

Every expression exposes `type`, `nullability`, and `nullExtendedBy`. `kind` is an
`ExpressionKind` enum. `inputs()` visits immediate expression operands; `lineage()`
returns contributing column bindings while preserving distinct relation occurrences.

A selected column takes its declared type and NULL fact, adjusted for outer joins at
that occurrence. Operators derive facts from their operands and dialect. Functions
use registered signatures. A compound result combines corresponding operand types.
SQLite MIN and MAX with one argument are `AggregateCall`; with two or more arguments
they are `FunctionCall`. PostgreSQL GREATEST and LEAST retain their non-NULL selection
semantics independently of registered functions with similar names.
MySQL temporal arithmetic derives its result family from the temporal input and
interval fields: a DATE with calendar-only units remains a date, while time fields
promote it to datetime. TIME plus calendar fields follows the selected release's
rules. `DateArithmeticRules` retains that distinction; modern releases also infer
dynamic parameter families from the interval's required inputs. Literal text is
retained without parsing or evaluating its date, time, or interval value.

`TypeDescriptor::identity` carries typed storage parameters; `unknown` denotes missing
static type information. No expression is evaluated to infer its runtime value.

Predicates remain separate expression trees in ON, WHERE, HAVING, and conditional
writes. The consumer combines these with expression facts when evaluating SQL,
solving constraints, or generating fixtures. Binding describes the value-producing
operation at each stage; it does not solve predicates over possible values.

## Diagnostics and transformations

Strict binding raises `SemanticException` for unresolved names or incompatible known
types. `strict: false` retains classified unresolved references and `Diagnostic`
objects, each with `reason`, `message`, and `source`. A structurally impossible request,
such as a known INSERT width mismatch, raises `InvalidSql` with an `InputViolation`
enum; it does not manufacture a valid Statement. Lexical and syntax errors are
reported by sql-parser. An unclassified construct is an implementation failure and
is not represented by a generic command or raw grammar payload.

`source` retains parser positions and original text for diagnostics. Semantic
operands determine serialization. Transformations belong to the Statement and
return a new validated snapshot. See [statements and serialization](statements.md).
