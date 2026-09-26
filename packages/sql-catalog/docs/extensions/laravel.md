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

## Unresolved values

A statement is compiled from every operation the analyzer can read, and only the parts it cannot read are left open. A comparison value that is not a literal null becomes a bound placeholder, whatever its type:

```php
function find(Request $request, array $ids)
{
    return DB::table('users')
        ->where('group_id', $request->input('group_id'))
        ->whereIn('id', $ids)
        ->limit($request->integer('limit'))
        ->get();
}
```

```sql
select * from `users` where `group_id` = ? and `id` in ({$}) limit {$}
```

The `in` list and the limit are gaps, and the statement is `incomplete-model` with `searchClosed` false. A gap keeps its origin, so a list that comes from request input is reported as external input.

- `where('column', null)` becomes `is null` and `whereIn('column', [])` becomes Laravel's empty-set condition only for literal values. Guards before the call are not evaluated, so a nullable value is a placeholder with a nullable type.
- `when` and `unless` produce the statement with and without the callback, since the condition is not evaluated. A builder with more than eight alternatives is left open and marked as cut short.
- A builder method the extension does not model leaves the whole statement open, because it can change any part of the SQL. The reports name the method in the gap.

## Supported operations

The standard overloads of these methods are supported when identifiers resolve and arrays are complete. Named and unpacked arguments are not supported yet.

| Area | Supported |
|------|-----------|
| Query creation | `DB::table` with an optional alias, `DB::connection(...)->table`, `table` on a `Connection`, `ConnectionInterface`, `DatabaseManager` or `ConnectionResolverInterface`, `from`, `Model::query` and static builder calls on models, `newQuery`, `toBase`, `getQuery` |
| Projection | `select`, `addSelect`, `selectRaw`, columns passed to the terminal call, `DB::raw` |
| Predicates | Scalar `where` and `orWhere`, including the boolean argument and the array form, null tests, `whereIn`, `whereNotIn`, between tests, `whereColumn`, `whereRaw`, nested closures and arrow functions, `when` and `unless` with closures |
| Clauses | Simple inner, left and right column joins, `groupBy`, `having`, `orHaving`, `havingRaw`, `orHavingRaw`, ordering including raw SQL, `latest`, `oldest`, `limit`, `offset`, `distinct` without arguments |
| Reads | `get`, `all`, `first`, `firstOrFail`, `sole`, `value`, `find` and `findOrFail` with a key or a list, `pluck`, `count`, `sum`, `avg`, `min`, `max`, `exists`, `doesntExist`, `cursor`, `lazy`, `chunk`, `each`, `paginate`, `simplePaginate` |
| Writes | Single-row and bulk `insert`, `insertOrIgnore`, `insertGetId`, simple `update`, `increment`, `decrement`, `delete` with or without a key |
| Eloquent models | `$table`, `$primaryKey`, `$perPage` and `$keyType` declared in the source or inherited, conventional table names for regular English plurals, standard authentication model ancestry |
| Eloquent scopes | `scopeX` methods declared in the source that use supported operations; `SoftDeletes` reads, `withTrashed()`, `onlyTrashed()` and `withoutTrashed()` |

- A raw fragment with an unknown binding array binds one open value per `?` in the fragment.
- `paginate` reports the count statement and the page statement, counting through a subquery when the query is grouped. The page offset is external input, since Laravel reads the page from the request.
- The window of `chunk`, `each` and `lazy` is a gap built by the loop.
- An Eloquent `with` keeps the main statement and adds one open statement for the eager load.
- An Eloquent `find` with a list of integer keys writes the keys into the statement, as `whereIntegerInRaw` does.
- Declare `$table` explicitly when the plural of a model name is irregular. Eloquent `update` requires `$timestamps = false`.

These forms are checked against Illuminate Database 13.33 with the MySQL, PostgreSQL and SQLite grammars. Known differences: an Eloquent `find([])` issues no query but is reported as `0 = 1`, and a negative literal such as `limit(-1)` stays open.

## Not supported

These calls stay in the catalog as incomplete statements, never as exact ones:

- Unknown builder methods and macros, subqueries, unions, JSON selectors, cursor pagination, relation queries, and the statements an eager load issues.
- Custom builders, global scopes, model boot hooks, unknown traits, implicit `$with` and `$withCount`, and model metadata that cannot be resolved.
- Local scopes with early returns, replacement builders or regrouped boolean logic.
- Timestamped updates, soft-delete writes, locks, and persistence calls such as `create`, `save`, `firstOrCreate` and `updateOrCreate`.
- Direct writes to builder properties and PHP references.

A relation whose receiver cannot be resolved may be reported with the sink `unmatched`. Check `searchClosed` as well as `exact` before relying on a Laravel statement.
