# Laravel

The `laravel` extension catalogs raw SQL sent through `DB` and `Connection`, and reconstructs the SQL of Query Builder and Eloquent calls without booting Laravel.

```console
vendor/bin/sql-catalog --extension pdo,laravel --dialect mysql app/ routes/
```

From PHP, pass `new AnalysisOptions(['pdo', 'laravel'], dialect: 'mysql')`.

## Calls

| Sink ID | Call | Reads |
|---------|------|-------|
| `laravel.db.<method>` | `DB::select`, `selectOne`, `scalar`, `insert`, `update`, `delete`, `statement`, `unprepared` | The SQL and its bound values. |
| `laravel.connection.<method>` | The same methods on `Illuminate\Database\Connection` | The SQL and its bound values. |
| `laravel.query.<method>` | Terminal methods of `Illuminate\Database\Query\Builder`, such as `get` or `update` | The SQL the builder compiles. |
| `laravel.eloquent.<method>` | Terminal methods of `Illuminate\Database\Eloquent\Builder` | The SQL the builder compiles. |
| `laravel.model.<method>`, `laravel.model.static.<method>` | Terminal methods called on a model, such as `User::all()` | The SQL the builder compiles. |

Every terminal method is recognised, but only the [supported operations](#supported-operations) produce SQL. The others are reported as incomplete statements.

## Dialect

Builder SQL depends on the database grammar. Select it with `--dialect`: `mysql`, `pgsql` or `sqlite`. A connection typed as `MySqlConnection`, `PostgresConnection` or `SQLiteConnection` selects its own grammar; facades and generic connections need the option. Raw SQL does not need it.

The analysis assumes Laravel's standard grammar and no table prefix. Custom grammars, prefixes and service provider changes are not read. Analyze connections with different grammars in separate runs.

Include the application's models and scopes in the analyzed paths. Laravel's own source is not needed.

## Example

```php
use Illuminate\Support\Facades\DB;

$query = DB::table('users');
$alias = $query;
$alias->where('active', 1);
$query->orderBy('id')->get(['id', 'name']);
```

With `--dialect mysql`, the `get()` call is catalogued as:

```sql
select `id`, `name` from `users` where `active` = ? order by `id` asc
```

with the bound value `1`. Aliases share the builder's state, clones get their own, and each branch produces its own statement. A builder returned by a helper in the analyzed source is followed.

## Supported operations

The standard overloads of these methods are supported when identifiers resolve and arrays are complete. Named and unpacked arguments are not supported yet.

| Area | Supported |
|------|-----------|
| Query creation | `DB::table`, `DB::connection(...)->table`, `Connection::table`, `Model::query` and static builder calls on models |
| Projection | `select`, `addSelect`, `selectRaw`, columns passed to the terminal call, `DB::raw` |
| Predicates | Scalar `where` and `orWhere`, null tests, `whereIn`, `whereNotIn`, between tests, `whereColumn`, `whereRaw`, nested closures and arrow functions |
| Clauses | Simple inner, left and right column joins, `groupBy`, `havingRaw`, ordering including raw SQL, non-negative literal `limit` and `offset`, `distinct` without arguments |
| Reads | `get`, `all`, `first`, `firstOrFail`, scalar `find`, `pluck`, `count`, `sum`, `avg`, `min`, `max`, `exists`, `doesntExist` |
| Writes | Single-row and bulk `insert`, `insertOrIgnore`, simple `update` and `delete` |
| Eloquent models | `$table` and `$primaryKey` declared in the source or inherited, conventional table names for regular English plurals, standard authentication model ancestry |
| Eloquent scopes | `scopeX` methods declared in the source that use supported operations; `SoftDeletes` reads, `withTrashed()` and `onlyTrashed()` |

Declare `$table` explicitly when the plural of a model name is irregular. Eloquent `update` requires `$timestamps = false`.

`where('column', $value)` compiles to `is null` when `$value` is null. When `$value` cannot be resolved, the SQL is left open rather than assuming `= ?`. The values of a complete `whereIn` array can be unknown while its placeholders are known.

## Not supported

These calls stay in the catalog as incomplete statements, never as exact ones:

- Unknown builder methods and macros, subqueries, unions, `when()` and other conditional builders, JSON selectors.
- Pagination, chunking, cursors, relation queries, eager loading, implicit `$with` and `$withCount`.
- Custom builders, global scopes, model boot hooks, unknown traits, and model metadata that cannot be resolved.
- Local scopes with early returns, replacement builders or regrouped boolean logic.
- Timestamped updates, soft-delete writes, and persistence calls such as `create`, `save`, `firstOrCreate` and `updateOrCreate`.
- Direct writes to builder properties and PHP references.

A relation whose receiver cannot be resolved may be reported with the sink `unmatched`. Operations that run several queries are not split into their statements. Check `searchClosed` as well as `exact` before relying on a Laravel statement.
