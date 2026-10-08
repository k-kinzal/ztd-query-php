# Architecture

This document follows one statement through mysql-memory, from the bytes a client sends to the rows it receives. It describes the parts and the ideas behind them, not every class. The classes named here are in the `MySqlMemory` namespace, under `src/`.

The design follows the server. Each step does what the matching step of MySQL does, in the same order, so that what a client observes, including which error is reported when a statement has several problems, is the server's. Where the order matters, the code cites the reference manual or notes that a rule was checked on a live server.

```text
client ──packets──▶ Server\Listener ─▶ Server\Client ─┐
                                                      │  COM_QUERY, COM_STMT_*, ...
in process ───────────────────────────────────────────▶ Session\Session
                                                         │ split into statements
                                                         │ parse (SQL Parser, release grammar, sql_mode)
                                                         │ resolve and type (SQL Semantics)
                                                         │ report problems in the server's order
                                                         ▼
                                                      Command\Dispatcher ─▶ a Command
                                                         │ queries: Plan\Planner ─▶ access paths
                                                         │          Iterator\Builder ─▶ row iterators
                                                         │          Evaluation ─▶ values of expressions
                                                         │ writes:  Storage ─▶ rows of Dictionary tables
                                                         ▼
                                                      Result\ResultSet | Result\Completion | Error\SqlError
```

## The server and the protocol

`Instance` is the server: its databases (`Dictionary\Dictionary`), its accounts (`Account\Accounts`), the global values of its system variables (`Session\Globals`), the catalog of the system variables of the emulated release, and the other server-wide objects (`Registry\Registry`: resource groups, foreign servers, spatial reference systems, tablespaces, the binary log, prepared XA branches). It holds no socket. Code in the test process uses it directly through `Instance::connect()`.

`Server\Listener` puts an `Instance` behind a TCP or Unix socket. It runs in one PHP process and serves its connections with `stream_select()`, one packet at a time, so statements of different connections never run at the same time. `bin/mysql-memory` builds an `Instance` from its options, opens a `Listener` and prints `ready ADDRESS`. `Server\Server::start()` runs that command as a child process and reads the address from that line.

`Server\Client` is one connection. It sends the initial handshake (protocol version 10, `mysql_native_password`), reads the handshake response, and opens a session for the user and database it names; the password is not checked. It then answers the commands of the command phase: `COM_QUERY`, `COM_INIT_DB`, `COM_PING`, `COM_RESET_CONNECTION`, `COM_QUIT` and the others, while `Server\Statements` answers the prepared statement commands (`COM_STMT_PREPARE`, `COM_STMT_EXECUTE`, `COM_STMT_SEND_LONG_DATA`, `COM_STMT_CLOSE`, `COM_STMT_RESET`). The statements of a `COM_QUERY` run in order, and every result but the last carries `SERVER_MORE_RESULTS_EXISTS`.

`Protocol` holds the encoding. `PayloadReader` and `PayloadWriter` read and write the integer and string encodings of the protocol. `Messages` builds the handshake, OK, ERR and EOF packets and the column definitions, and `Binary` encodes rows in the binary protocol and decodes the parameter values a client binds.

## Sessions

`Session\Session` is the state of one connection: its current database and variables (`Session\Variables`), its diagnostics area (`Session\Diagnostics`), its transaction (`Session\Transaction`), and its locked tables, open handlers and prepared statements. A session turns a text into replies:

1. **Split.** The text is split into statements where the server would split it. An empty text is `ER_EMPTY_QUERY`; a text that does not parse is reported as the syntax error of its first bad statement.
2. **Parse.** Each statement is parsed by SQL Parser with the grammar of the emulated release, under the lexical modes of the session's `sql_mode` (`ANSI_QUOTES`, `PIPES_AS_CONCAT`, `HIGH_NOT_PRECEDENCE`, `NO_BACKSLASH_ESCAPES`, `IGNORE_SPACE`). `Session\Syntax` reports a refused token as `ER_PARSE_ERROR` with the text the server quotes, and adds the checks the server makes while parsing: a `?` marker outside a prepared statement, a temporal literal that is not valid, a statement that only debug builds accept.
3. **Resolve and type.** SQL Semantics analyzes the parse tree. See below.
4. **Report problems.** SQL Semantics records each problem it finds (an unknown column, a wrong argument count, a `GROUP BY` violation) as a fact of the statement instead of failing. `Session\Problems` raises the first of them the server would report. The server finds problems at different stages: while it parses, at the end of each query block, while it resolves the names of each clause in a fixed order, and while it runs. `Session\Problem\Stages` and `Session\Problem\Locations` place each problem at its stage and position, and `Session\Problem\Errors` maps it to the server error.
5. **Dispatch and run.** `Command\Dispatcher` chooses the command of the statement's kind, and the command runs it. A statement kind with no command is refused with error 1235 (`ER_NOT_SUPPORTED_YET`).

Around each statement the session clears or keeps the diagnostics area, as the statement's kind decides (`SHOW WARNINGS` keeps it), records `ROW_COUNT()`, and keeps the statement atomic: `Session\Transaction` keeps the rows of each table before the statement first changes it, and puts them back when the statement fails. Inside an explicit transaction it keeps the rows each table had when the transaction first changed it, for `ROLLBACK`, and for each savepoint the rows of the tables changed after it, for `ROLLBACK TO SAVEPOINT`. It also tracks the state of an XA transaction.

## Analysis by SQL Semantics

mysql-memory does not resolve names or types itself. It hands each parse tree to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-mysql/README.md), which builds a typed statement model (an `Operation`) whose facts tell, for every node, what it means: the table or column a name resolves to, the select item an alias names, the resolved type and nullability of every expression, and the problems found.

The analysis takes these inputs from the session:

- **The language profile**: the grammar release of the instance, the lexical mode read from `sql_mode`, and the native parameter style (`?` markers). The session keeps one analyzer for each lexical mode it has used.
- **The declarations**: every table and view of the `Dictionary`, as the declarations SQL Semantics binds against. The context is complete: a name that is not declared does not exist.
- **The search path**: the current database, where unqualified names are looked up.
- **The session settings** (`Session::resolution()`): the connection and server collations, the default collation of each database, `div_precision_increment`, `group_concat_max_len`, the types of the user variables, the types of the values bound to parameter markers, whether `NO_UNSIGNED_SUBTRACTION` is set, and the client character set. These decide the types SQL Semantics derives, as they decide them in the server.

Before the analysis, the views whose tables have changed since they were created are resolved again (`Plan\Views`), so that a view always reads the current definitions of its tables. A construct SQL Semantics has no rule for yet is reported as error 1235.

The commands then read the facts instead of the raw tree. The planner and the expression compiler (`Evaluation\Compile\Settings`) also read the session's `sql_mode`, current database, release and the same settings.

## Commands

A command (`Command\Command`) runs one kind of resolved statement in a session and answers a `Result\Reply`. The commands are grouped by what they act on:

| Directory | Statements |
|-----------|------------|
| `Command` | queries (`QueryCommand`), `SET`, `USE` and databases, `DO`, `SHOW TABLES`, `SHOW WARNINGS` and `SHOW ERRORS`, transactions |
| `Command\Write` | `INSERT`, `REPLACE`, `UPDATE`, `DELETE` of one or several tables |
| `Command\Definition` | `CREATE`, `ALTER`, `RENAME`, `DROP` and `TRUNCATE TABLE`, indexes, `ALTER DATABASE` |
| `Command\View` | views, and writes through views |
| `Command\Program` | procedures, functions, triggers and events, `CALL` |
| `Command\Account` | users, roles, passwords, `GRANT` and `REVOKE` |
| `Command\Transaction` | savepoints and XA |
| `Command\Access` | `LOCK TABLES`, `HANDLER`, `LOAD DATA`, `IMPORT TABLE` |
| `Command\Condition` | `SIGNAL`, `RESIGNAL`, `GET DIAGNOSTICS` |
| `Command\Prepared` | `PREPARE`, `EXECUTE`, `DEALLOCATE PREPARE` |
| `Command\Show` | `SHOW` of tables, columns, keys and server catalogs |
| `Command\Explain` | `EXPLAIN` |
| `Command\Maintenance` | `CHECK`, `ANALYZE`, `OPTIMIZE`, `REPAIR`, `CHECKSUM TABLE`, key caches |
| `Command\Admin`, `Command\Replication` | tablespaces, resource groups, servers, plugins, `FLUSH`, binary log and replication |

A query is planned and executed (below). A write evaluates its rows with the same machinery and stores them through `Storage`. A definition changes the `Dictionary`. The statements that commit implicitly commit the open transaction first, as the server does.

Commands of statements that need what an emulator does not have (files, plugins, replicas, other server processes) answer what a server without them answers: an empty list, or the error such a server reports.

## Planning

`Plan\Planner` turns a query into a tree of access paths (`Plan\Path\AccessPath`), as the server builds its access path tree, and answers a `Plan\QueryPlan`: the root path and the name and type of each output column. There is no optimizer: every table is scanned, every join is a nested loop, and grouping, sorting and duplicate removal read their whole input. The plan decides only what is computed, in the order the server computes it.

- `Plan\Blocks` plans one query block in the order the server applies its clauses: `FROM`, `WHERE`, grouping, `HAVING`, the select list, `DISTINCT`, `ORDER BY` and `LIMIT`.
- `Plan\Relations` plans the `FROM` clause: base tables, derived tables (`LATERAL` ones inside the frame of the block), common tables, views, joins with `ON`, `USING` and `NATURAL`.
- `Plan\Grouping` plans `GROUP BY`, the aggregates, `WITH ROLLUP` and `GROUPING()`.
- `Plan\Recursion` plans a recursive common table expression as a nonrecursive part and a part that reads the rows of the last iteration.
- `Plan\Views` plans a reference to a view as its query read like a derived table, and decides what the column metadata reports for it.
- `Plan\ConstantTables` and `Plan\ColumnOrigin` decide the metadata of each result column (its table, original column, key flags), which in the server depends on whether a table is read as a constant and whether rows pass through a temporary table.

The paths are sources (`TableScan`, `Inline` for `VALUES`, `SingleRow`, `ZeroRows`, `WorkingTable` for recursion), combinations (`NestedLoopJoin`, `SetOperation`, `RecursiveUnion`) and transforms (`Filter`, `Aggregate`, `Project`, `Distinct`, `Sort`, `Limit`, `Materialize`).

## Row iterators

`Iterator\Builder` turns the access path tree into a tree of row iterators, one for each path. An iterator (`Iterator\RowIterator`) is started for a frame with `init()` and then read with `read()` until it answers `null`. A row is a list of values of a fixed width. `Command\Output` runs the root iterator and writes each value as the text the server sends for it.

A table scan reads the rows of a table in the order of its clustered index, as InnoDB does: the primary key, else the first unique key of `NOT NULL` columns, else insertion order. A nested loop join reads its inner input again for each outer row. A materializing iterator computes a derived table or a subquery when it is first read, in a frame of its own.

## Expression evaluation

`Evaluation\Compile\Compiler` compiles each expression of a statement once, before any row is read, into an `Evaluation\Evaluable`: an object that knows its resolved type (`Typing\Domain`) and computes its value from a frame. The compiler reads the facts of SQL Semantics for each node: the column a name resolves to and its position in the row (`Evaluation\Scope`), the select item an alias names, the type the node resolves to. An expression form or a function the emulator does not evaluate is refused here with error 1235, before the statement changes anything.

- `Evaluation\Leaf`: constants, column reads, outer references, user and system variables, assignments, clocks.
- `Evaluation\Operator`: arithmetic, comparisons, logic, bit operations, `CASE`, conversions, date arithmetic, collation weights.
- `Evaluation\Function`: the library of built-in functions, by name.
- `Evaluation\Subquery`: scalar subqueries, `EXISTS`, `IN`, `ANY` and `ALL`.
- `Evaluation\Aggregate`: the accumulators of the aggregate functions.

`Evaluation\Frame` is the row an expression reads and the frames of the blocks around it, for correlated subqueries. `Evaluation\Context` is what one statement is evaluated in: the `sql_mode`, the variables, the diagnostics area where warnings go, and the instant the statement started, which every `NOW()` of the statement reads. `Evaluation\Convert` reads an operand of one type in the context of another, with the warnings the server raises (for example a string read as a number).

## Values, types and storage

A value at run time is a PHP `int`, `float` or `string`, or `null` for SQL NULL. Its `Typing\Domain` says how to read it: integers as signed or unsigned 64-bit values (`Value\Integer`), exact decimals as canonical decimal text (`Value\Decimal`), doubles (`Value\Real`, which also writes them as the server does), dates and times as canonical text (`Value\Temporal`, `Value\Calendar`, `Value\Interval`), strings as the bytes of their character set (`Value\Encoding`), JSON as a parsed document (`Value\Json`). `Value\Order` compares and keys values for sorting, grouping and unique keys, strings by their collation through ICU (`Typing\Ordering`). `Typing\Collations` decides the collation of an operation over several strings, and `Typing\Declared` the domain of a declared column type.

`Dictionary` holds the definitions. A `Dictionary\Schema` is a database with its tables, views, routines, triggers and events. A `Dictionary\StoredTable` pairs a `Dictionary\TableDefinition` (columns, keys, options, and the declaration SQL Semantics binds against) with a `Storage\Heap` (its rows under row numbers, and its `AUTO_INCREMENT` counter).

`Storage` writes rows. `Storage\Store` converts a value into what a column stores, as the server converts a value for the field it writes: clipping, rounding or truncating it with a warning, or failing under a strict `sql_mode`. `Storage\Times` and `Storage\Members` do the same for temporal, `ENUM` and `SET` columns. `Storage\Writer` keeps the unique keys and the `AUTO_INCREMENT` counter.

## Errors

Every error the emulator reports is a `Error\SqlError`: the error number, the SQLSTATE and the message, as the server reports them. The errors are grouped into enums by family (`AccountError`, `AdministrationError`, `DataError`, `ProgramError`, `QueryError`, `SchemaError`, `StatementError`), each case backed by its error number. Their SQLSTATEs and message formats are in `resources/errors.php`, copied from the server error reference, so a message is built from the same format as the server's. `Error\ErrorNumbers` finds the error of a number.

In process, `Session::query()` throws the `SqlError` of the first statement that fails. Over the protocol, `Server\Client` sends it as an ERR packet. Any other exception is a fault of the emulator: over the protocol it is sent as error 1105 with a message beginning `mysql-memory internal error:`, and in process it is thrown as it is.
