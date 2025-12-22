# Zero Table Dependency (ZTD) Mechanism

## Overview

**Zero Table Dependency (ZTD)** is a SQL testing methodology that eliminates table dependencies. It uses a real database engine while replacing all physical table reads and writes with shadow tables implemented as CTEs (Common Table Expressions).

ZTD enables SQL unit testing without migrations, seeding, or cleanup operations.

## References

- [Ultra-fast SQL Unit Testing with rawsql-ts/pg-testkit](https://zenn.dev/mkmonaka/articles/c2413d99ae67bb)
- [rawsql-ts GitHub Repository](https://github.com/mk3008/rawsql-ts)
- [pg-testkit Package](https://github.com/mk3008/rawsql-ts/tree/main/packages/drivers/pg-testkit)

---

## Core Philosophy: SQL as Pure Functions

ZTD treats SQL queries as pure functions:

- **Input**: Fixture data (the state of tables before query execution)
- **Output**: Query result set
- **No Side Effects**: Physical tables are never modified

This functional approach enables deterministic, isolated, and parallelizable tests.

---

## Core Mechanisms

ZTD operates through two primary mechanisms:

### 1. CTE Shadowing

CTE Shadowing replaces physical tables with CTEs of the same name. Since **CTEs take precedence over physical tables** in query resolution, actual table references are intercepted and replaced with test data.

The real database engine still handles:
- SQL parsing
- Type inference
- Query planning
- Parameter binding

But physical table access is blocked by the CTE.

#### Transformation Example

**Before (original query):**
```sql
SELECT email FROM users WHERE id = $1
```

**After (with CTE Shadowing):**
```sql
WITH users AS (
  SELECT 1 AS id, 'alice@example.com' AS email
  UNION ALL
  SELECT 2 AS id, 'bob@example.com' AS email
)
SELECT email FROM users WHERE id = $1
```

### 2. Result Select Query (RSQ)

Result Select Query transforms INSERT, UPDATE, DELETE, and MERGE operations into equivalent SELECT statements that simulate their outcomes.

---

## Query Transformation Details

The rawsql-ts library implements query transformation through dedicated converter classes in `packages/core/src/transformers/`.

### INSERT Conversion (InsertResultSelectConverter)

**Transformation Process:**

1. **Query Preparation**: VALUES-based inserts are rewritten into `INSERT ... SELECT` format via `InsertQuerySelectValuesConverter.toSelectUnion()` before further processing.

2. **Column Resolution**: Resolves columns through:
   - Explicit column lists from the INSERT statement
   - Table definition metadata when columns are omitted
   - Validates column counts match SELECT output

3. **CTE Construction**: Generates CTEs to wrap the source SELECT query and include fixture tables.

4. **Sequence Defaults**: Defaults invoking `nextval` are rewritten into deterministic expressions (e.g., `row_number() over ()`).

5. **Output Generation**:
   - With RETURNING clause: Expands wildcards, rewrites column references, applies default values
   - Without RETURNING clause: Produces `COUNT(*)` query

**Example:**
```sql
-- Before
INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')

-- After (with RETURNING *)
WITH users AS (... existing fixture data ...)
SELECT
  row_number() OVER () AS id,
  'Alice' AS name,
  'alice@example.com' AS email
```

### UPDATE Conversion (UpdateResultSelectConverter)

**Transformation Process:**

1. **Target Table Extraction**: Extracts target table from UPDATE clause (must be a `TableSource`, not derived expression).

2. **FROM Clause Handling**: When explicit FROM clause exists, cross-joins with target table to maintain column accessibility. Joins within FROM are preserved.

3. **Column Expression Building**: For each RETURNING item:
   - Expands wildcards using metadata
   - Checks SET expressions for column overrides
   - Preserves original column references if not updated

4. **Output Generation**:
   - With RETURNING: SELECT reflecting updated columns
   - Without RETURNING: `COUNT(*)` query

**Example:**
```sql
-- Before
UPDATE users SET email = 'new@example.com' WHERE id = 1

-- After (with RETURNING *)
WITH users AS (... existing fixture data ...)
SELECT
  id,
  CASE WHEN id = 1 THEN 'new@example.com' ELSE email END AS email,
  name
FROM users
WHERE id = 1
```

### DELETE Conversion (DeleteResultSelectConverter)

**Transformation Process:**

1. **FROM/JOIN Construction**: Preserves DELETE target table. USING clause sources become CROSS JOINs.

2. **Column Reference Resolution**: Parses namespaces, builds context maps for aliases, validates column existence.

3. **Output Generation**:
   - With RETURNING: SELECT with returning expressions, wildcards expanded
   - Without RETURNING: `COUNT(*)` query

**Example:**
```sql
-- Before
DELETE FROM users WHERE id = 1

-- After (with RETURNING *)
WITH users AS (... existing fixture data ...)
SELECT * FROM users WHERE id = 1
```

### MERGE Conversion (MergeResultSelectConverter)

Converts MERGE queries into SELECT statements that count affected rows by modeling each WHEN clause as a separate action.

**Action-Specific Conversions:**

- **MATCHED (UPDATE/DELETE)**: Creates INNER JOIN between target and source using ON condition
- **NOT MATCHED (INSERT)**: Uses NOT EXISTS subquery to detect missing matches
- **NOT MATCHED BY SOURCE (DELETE)**: Inverted NOT EXISTS against source

**Process:**
1. Build individual SELECT for each WHEN clause
2. Combine via UNION ALL
3. Wrap in `COUNT(*)` query

---

## CTE Injection Mechanism

### CTEInjector

The `CTEInjector` class handles inserting CTEs into queries:

1. **Validation**: Returns original query unchanged if CTE array is empty
2. **Collection**: Gathers existing CTEs via `CTECollector`, merges with provided CommonTables
3. **Resolution**: `CTEBuilder` eliminates duplicates and arranges in dependency order
4. **Injection**: Assigns resolved WithClause to query

### CTEBuilder

Manages CTE construction through:

1. **Duplicate Resolution**: Validates CTEs with same names have identical definitions
2. **Dependency Graph Construction**: Maps table references and identifies recursive CTEs (self-referencing)
3. **Topological Sorting**: Orders CTEs so recursive CTEs come first, then remaining tables sorted by dependency depth
4. **Circular Reference Detection**: Throws error if circular reference detected

---

## ResultSelectRewriter

The main orchestrator (`packages/testkit-core/src/rewriter/ResultSelectRewriter.ts`) transforms queries through a pipeline:

**Statement Type Routing:**

| Statement Type | Handler |
|---------------|---------|
| INSERT | `InsertResultSelectConverter.toSelectQuery()` |
| UPDATE | `UpdateResultSelectConverter.toSelectQuery()` |
| DELETE | `DeleteResultSelectConverter.toSelectQuery()` |
| MERGE | `MergeResultSelectConverter.toSelectQuery()` |
| SELECT | Direct fixture injection |
| Complex SELECT | Normalized via `QueryBuilder.buildSimpleQuery()` |
| CREATE TEMP...AS SELECT | Inner query receives fixture injection |
| Other DDL | Returns null (ignored) |

**Post-Processing:**

1. **Schema Qualifier Rewriting**: Traverses table references, updates to use fixture aliases
2. **Column Reference Updates**: Recursively processes subqueries, CTEs, window functions
3. **Alias Sanitization**: Normalizes table names, replaces dots with underscores

---

## Fixture System

### Fixture Architecture

Located in `packages/testkit-core/src/fixtures/`:

| File | Purpose |
|------|---------|
| DdlFixtureLoader.ts | Loads DDL files from directories |
| FixtureProvider.ts | Provides and merges fixtures |
| FixtureStore.ts | Stores fixture data |
| TableDefinitionSchemaRegistry.ts | Manages table schemas |
| TableNameResolver.ts | Resolves table names |

### DdlFixtureLoader

**File Discovery:**
- Recursively scans configured directories for SQL files
- Default extension: `.sql`
- Validates directories exist before scanning

**Conversion Process:**
- Uses `DDLToFixtureConverter.convert()` to process CREATE TABLE and INSERT statements
- Extracts column metadata (name, type, default values)
- Extracts fixture rows from INSERT data

**Caching Strategy:**
- Static cache avoids reprocessing identical configurations
- Cache key: normalized paths + extensions + resolver settings

**Deduplication:**
- Compares canonical table keys to prevent duplicate fixtures

### FixtureProvider (DefaultFixtureProvider)

**Core Functions:**

1. **Fixture Resolution**: Merges table definitions with baseline and runtime override fixtures
2. **Row Validation**: Ensures required columns without defaults are provided
3. **Type Coercion**: Converts values to database-compatible types:
   - strings, numbers, bigints, buffers
   - JSON-serialized objects
   - Booleans as 0/1
4. **Name Normalization**: Handles schema-qualified identifiers via `TableNameResolver`

### Fixture Layering (Priority Order)

1. **DDL files** - Parsed at client construction
2. **Manual tableDefinitions/tableRows** - Merged after DDL
3. **withFixtures()** - Overlays scenario-specific data (highest priority)

Later layers override earlier ones.

---

## API Usage (pg-testkit)

### Three Primary APIs

```typescript
// 1. Create isolated client with lazy connection
const client = createPgTestkitClient({
  connectionFactory: () => new Client({ connectionString: process.env.PG_URL! }),
  tableDefinitions: [...],
  tableRows: [...]
});

// 2. Wrap pg.Pool (transactions/savepoints execute on raw client)
const pool = createPgTestkitPool(process.env.PG_URL!, {
  tableDefinitions: [...],
  tableRows: [...]
});

// 3. Wrap existing connection
const raw = new Client({ connectionString: process.env.PG_URL! });
await raw.connect();
const wrapped = wrapPgClient(raw, { tableDefinitions, tableRows });
```

### Scoped Fixtures

```typescript
const scoped = client.withFixtures([{
  tableName: 'users',
  rows: [{ id: 2, email: 'bob@example.com' }]
}]);
```

### DDL-Based Configuration

```typescript
ddl: {
  directories: [path.join(__dirname, '..', 'ztd', 'ddl')],
  extensions: ['.sql']
}
```

---

## Implementation Characteristics

| Feature | Description |
|---------|-------------|
| Lazy Connection | Opens database connection only on first query execution |
| Parallel Test Safety | No shared schema state; each client has independent fixtures |
| Transaction Support | Raw transaction/savepoint commands preserved |
| Temporary Table Support | `CREATE TEMPORARY ... AS SELECT` survives rewrite pipeline |
| Parameter Handling | `$1`-style placeholders normalized before rewriting, restored before execution |

---

## Benefits

1. **No Migration Required**
   - Rapidly iterate on table definitions during development
   - SQL logic correctness guaranteed through unit tests

2. **No Cleanup Required**
   - Each test execution is independent
   - No residual data left in the database

3. **Parallel Test Execution**
   - No persistent tables or shared schema state
   - Each query works against isolated dataset

4. **Low Adoption Cost**
   - Self-contained test kit
   - Dynamically modifies existing SQL resources
   - Production code remains unaware of testing infrastructure

5. **Real Database Engine**
   - Unlike mocks, uses actual database for SQL parsing and type inference

---

## Limitations

ZTD is **not suitable** for testing:

| Category | Reason |
|----------|--------|
| Stored Procedures | Executed inside database, not subject to CTE shadowing |
| Triggers | INSERT/UPDATE/DELETE triggers do not fire |
| Views | Existing views may not be replaceable with CTEs |
| Performance/Tuning | No actual table access, I/O and index effects cannot be measured |

---

## Execution Modes

### Default Mode (Fixture-based)
Rewrites operations as SELECT queries without touching real tables.

### Traditional Mode
Set `ZTD_EXECUTION_MODE=traditional` to execute actual PostgreSQL behavior including locks and isolation levels, with schema isolation and automatic cleanup.

---

## Summary

Zero Table Dependency (ZTD) is a SQL testing methodology with the following characteristics:

1. **No physical table dependency** - Tables are shadowed with CTEs
2. **Uses real database engine** - Not mocks, but actual SQL processing
3. **Tests SQL as pure functions** - Focus only on input (fixtures) and output (query results)
4. **Fast and parallelizable** - No migration or cleanup required
5. **Low adoption cost** - Can be introduced without modifying existing code
