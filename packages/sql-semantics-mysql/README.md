# SQL Semantics for MySQL

Requires PHP 8.1 or later. See [grammar releases and usage](../sql-semantics/) for the common interface.

    composer require k-kinzal/sql-semantics-mysql

```php
use SqlSemantics\Platform\MySql\Semantics;

$semantics = new Semantics();
$context = $semantics->context(['CREATE TABLE app.accounts (id INTEGER)']);
$ir = $semantics->analyze('SELECT id, accounts.id FROM app.accounts', $context);

echo $ir->toString();
```

The checked complete domain includes signed integers and native VARCHAR projections. Configure the grammar release and lexical profile before declaring the catalog. Use a database-qualified relation name for binding or configure a current database in the mode.

A declared session-temporary table has its own `ExternalRelationKind::TemporaryTable` value. Reads and writes bind to the temporary table when it shares an absolute name with a persistent table. A resolved `RENAME TABLE` continues to address the persistent table of that name. Evaluate the IR in a connection with the corresponding temporary table declaration.

`DROP VIEW` binds in the persistent catalog. A session-temporary table at the same absolute name remains available after removing the view. `DROP VIEW IF EXISTS` can represent an absent persistent view even if a temporary table uses that name.

`DROP TABLE` keeps each persistent or session-local identity. `DROP TEMPORARY TABLE` restricts lookup to session tables and leaves the transaction open. Use `IF EXISTS` to allow a known absent name in the selected namespace.

`CREATE TABLE … LIKE` resolves the source table in the session's visible namespace and records the destination separately. Use `CREATE TEMPORARY TABLE … LIKE` for a session-local destination. The server creates an empty table using the original column and index definitions.

`CREATE TABLE … AS SELECT` accepts signed 32-bit integer, Boolean and NULL literals, including a single `CAST(… AS SIGNED)`. A projection over a single resolved table can also select its signed INT and checked utf8mb4_bin VARCHAR fields. It supports integer comparisons and NULL tests of those fields. Construction checks the effective ordered heading after applying explicit storage overrides. The native width is at most 1,017 fields. The logical and clustered record checks count each projected occurrence, including repeated fields, length prefixes and nullability. Each selected field inherits its native storage, default and nullability. Use the corresponding source table and session when executing the SQL. The returned `Schema\Query\CreateTableAs` retains the absolute target, its lifetime and the completed `Select`. The selected MySQL release determines the native storage of each projected column. Use `CREATE TEMPORARY TABLE … AS SELECT` to keep the table definition for the session. InnoDB rolls back the populated rows on a transaction rollback and keeps the temporary definition. A source-free declaration can list explicit signed column definitions before AS. Declaration-only fields precede the projected fields. A declared field matching a projected name supplies its storage, default and nullability.

Declare `COLLATE utf8mb4_bin` on the VARCHAR field or as the table default to read the field by its native position. The declaration can name `CHARACTER SET utf8mb4` explicitly. A field with its own character-set clause needs its own collation. Bare, qualified and wildcard projections of the same field retain the same reference and its declared VARCHAR length. Values retain trailing spaces, and comparisons for ORDER BY and DISTINCT use the collation’s PAD SPACE rules. Use IS NULL and IS NOT NULL on the resolved field in a result, a WHERE filter or a join condition.

Compare a resolved VARCHAR field with another field, a plain text literal or NULL using `=`, `<>`, `<`, `<=`, `>`, `>=` or `<=>`. The field fixes the collation. Ordinary comparisons propagate NULL and `<=>` produces an integer truth value for every operand pair. Use these comparisons as projected results or in SELECT, UPDATE and DELETE filters and local join conditions.

An ordinary single-table UPDATE can assign a resolved VARCHAR field, a plain text literal or NULL to another declared VARCHAR field. The target layout retains the character capacity and nullability. Assignments run in written order, so a later read sees the value stored by an earlier assignment. The native empty SQL mode applies character truncation and substitutes an empty string for NULL in a required field, with warnings.

A SELECT can read an external view declared in the catalog. Supply its defining statement and the declarations for its inputs. The IR records the resolved database, name and view kind, and directly projected fields retain supported native types and collations. The server evaluates the view when running the emitted SQL. Mutation targets require a base table.

Create or replace a persistent view over a checked ordinary SELECT. The IR exposes the effective ordered catalog heading, view algorithm and SQL security policy. A definition can read a single declared base table or return independent literals. MySQL 9.1 also supports `CREATE VIEW IF NOT EXISTS`.

Issue `DROP VIEW` against absolute persistent view identities. Supply the referenced declarations for required targets. `IF EXISTS` also admits a known absence in a named database. The emitted statement retains MySQL's release-specific removal and transaction effects.

An ordinary INSERT VALUES can write text, signed integer and NULL literals to an explicit list of resolved native destinations. Each row follows the destination order. With empty SQL mode, NULL in a required VARCHAR causes a single-row INSERT to fail. A multi-row INSERT stores the implicit empty value and emits a warning.

An ordinary `INSERT ... SELECT` can write native VARCHAR fields, text literals, and NULL alongside signed values from a checked SELECT. The field order determines the destination of each expression. `DISTINCT` applies before the target’s storage conversion. Under empty SQL mode this form substitutes the implicit empty string for NULL assigned to a required VARCHAR and warns, including when the SELECT produces one row.

COUNT can read a native VARCHAR field in a global or signed-key grouped query. COUNT skips NULL and COUNT DISTINCT counts the field’s case-sensitive PAD SPACE equivalence classes.

For a grouped projection, `HAVING` can compare signed keys and literals with numeric `COUNT`, `SUM`, `MIN` or `MAX` results. Its operands are resolved before the grouped value is published. An output alias refers to its computed value, while a grouping field of the same name takes precedence under the MySQL resolution rules.

MIN and MAX over a native VARCHAR field use its PAD SPACE ordering. They return the original bytes of an extremal value, including trailing spaces, or NULL when no non-NULL value enters the aggregate. Equal extremal values can have different stored bytes.

An independent derived input in a join can export native VARCHAR fields alongside signed fields. Outer operands read the exact derived occurrence and output position. Native type, collation and character capacity are derived from the checked inner projection.

An ordinary InnoDB `CREATE TABLE` can declare `VARCHAR(n)` with the checked `utf8mb4_bin` collation, including inheritance from an explicit table default. `Schema\CharacterStorage` carries the declared capacity in characters. Each column keeps its own nullability and optional text or NULL default. Excess ASCII whitespace in a default remains in the emitted SQL so the server can report its truncation. Defaults that discard significant characters are rejected during construction.

An ordinary InnoDB declaration accepts up to 1,017 columns. With the stock strict InnoDB profile and 16 KiB pages, the clustered record must be smaller than 8,126 bytes. The checked width includes fixed storage, the local VARCHAR representation, nullable bits and the implicit row identifier when no primary key is declared. Stock MySQL 5.6 allows a larger record declaration with a warning. It can still refuse an oversized row at insertion.

Use `TINYINT`, `SMALLINT`, `MEDIUMINT`, `INTEGER` or `BIGINT` for an ordinary signed declaration. The model checks the exact signed range of a literal default, including both BIGINT endpoints, and retains its decimal value.

An ordinary signed `PRIMARY KEY` retains the ordered positions of its required fields. Inline and table clauses resolve to the same key type. The column layout records effective defaults for the selected release, including the zero default that MySQL 5.6 can assign when a table key makes a column required. Key terms retain descending storage from MySQL 8.0.

Apply an explicit `COLLATE utf8mb4_bin` to a native VARCHAR field or a plain text literal. The explicit role retains the operand’s bytes and takes precedence over implicit field and literal collations when selecting a supported comparison.

Signed integer `BETWEEN` and `NOT BETWEEN` preserve inclusive bounds, NULL and signed 64-bit range literals. A projected range test has MySQL's native integer truth result.

On MySQL 8.0 and later an ordinary `WITH` retains the ordered definition bodies and the resolved relation and field positions in each local projection. Independent nested definitions keep their own scope. Native markers in projected signed casts follow the definition order and then the result query.

`RENAME TABLE` publishes `Ir\Schema\Alter\RenameTable` with the move list in native execution order. Every move records its current name, destination and original table identity. A swap through an intermediate name retains the same origin when that name becomes the next source. Supply the declarations and a named current database for unqualified operands. The checked operation covers persistent base tables.

`TRUNCATE TABLE` publishes `Ir\Schema\Truncate\TruncateTable` with the resolved persistent or session-local `TableIdentity`. Rendering retains native allocation reset and implicit commit. A same-named permanent table and its foreign-key dependencies stay separate when the statement resolves to a temporary table.

Concrete types are under `SqlSemantics\Platform\MySql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
