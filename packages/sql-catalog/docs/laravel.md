# Laravel support

Enable the extension and choose the grammar used by the application's database:

```sh
vendor/bin/sql-catalog --extension laravel --dialect mysql app/ routes/
```

For the library API, pass `new AnalysisOptions(['laravel'], dialect: 'mysql')`.
The dialect can be `mysql`, `pgsql` or `sqlite`. A concrete Illuminate
`MySqlConnection`, `PostgresConnection` or `SQLiteConnection` also identifies
its grammar. Facades and generic connections require the option; a connection
name such as `reporting` does not identify a driver. Raw SQL calls work without
this option.

The selected dialect assumes Laravel's standard grammar and an empty table
prefix for every connection being analyzed. Custom grammars, prefixes and
service-provider changes are not inferred from runtime configuration. Analyze
connections with different grammars separately. Include application model and
scope declarations in the input; installing or scanning Laravel's vendor tree
is not required.

The Laravel extension registers its call transformations, query compiler and
framework type relations through the [source model API](extensions.md). The core
contains no Laravel-specific dispatch or SQL compilation rules.

## How builder queries are derived

```php
use Illuminate\Support\Facades\DB;

$query = DB::table('users');
$alias = $query;
$alias->where('active', 1);
$query->orderBy('id')->get(['id', 'name']);
```

With the MySQL grammar, the `get()` call produces:

```sql
select `id`, `name` from `users` where `active` = ? order by `id` asc
```

The catalog retains the bound value `1`, the execution call site, its tables,
findings and derivation evidence. Identifiers and values use the existing value
domains, so parameters can be resolved through source-declared callers.

The backward slice includes operations that may mutate the receiver. Aliases
share its state, clones get independent state, and branches preserve alternative
SQL statements. A builder returned by a source-declared helper can be followed.
Unknown calls that receive a tracked builder leave its effects open. The
analyzer does not execute PHP or descend through Laravel to PDO.

## How unresolved values are reported

A statement is compiled from every operation the analyzer could read, and only
the part it could not read is left open. A comparison value that is not a
literal null is compiled as a bound placeholder, whatever its static type, and
the binding shows the type the value may take:

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

The `in` list and the limit are gaps because their length and value come from
outside the analyzed code; their origin is kept, so a list that arrives through
a request superglobal is reported as external input. The statement is reported
as `incomplete-model` rather than `resolved`, and `searchClosed` is false.

Laravel's null normalization, which turns `where('column', null)` into
`is null`, and its empty-set constants for `whereIn('column', [])` are
reconstructed from literals only. The null and emptiness guards that usually
precede such calls are not evaluated, so a nullable value is reported as a
placeholder with a nullable binding type, not as an `is null` alternative.

A builder method the extension does not model leaves the whole statement open,
because an unknown method can change any part of the SQL. The gap quotes the
method name in the JSON and HTML reports. A conditional callback through `when`
or `unless` produces both outcomes, since the condition is not evaluated; a
receiver that accumulates more than eight alternatives is marked open and the
statement is reported as cut short.

## Supported operations

Support applies to the standard overloads below with resolvable identifiers and
complete array shapes. Named or unpacked arguments are currently incomplete.

| Area | Supported forms |
| --- | --- |
| Query creation | `DB::table` with an optional alias, `DB::connection(...)->table`, `table` on an Illuminate connection, `ConnectionInterface`, `DatabaseManager` or `ConnectionResolverInterface`, `from`, model `query` and static builder calls, `newQuery`, `toBase`, `getQuery` |
| Projection | `select`, `addSelect`, `selectRaw`, terminal column arguments, `DB::raw` expressions |
| Predicates | Scalar `where` / `orWhere` including the explicit boolean argument and the array form, null tests, `whereIn` / `whereNotIn`, between tests, `whereColumn`, `whereRaw`, nested predicate closures and arrow functions, `when` / `unless` with closures |
| Clauses | Simple inner/left/right column joins, `groupBy`, `having` / `orHaving`, `havingRaw` / `orHavingRaw`, ordering including raw SQL, `latest` / `oldest`, limits and offsets, argument-free `distinct` |
| Reads | `get`, `all`, `first`, `firstOrFail`, `sole`, `value`, `find` / `findOrFail` with a scalar or a list, `pluck`, `count`, `sum`, `avg`, `min`, `max`, `exists`, `doesntExist`, `cursor`, `lazy`, `chunk`, `each`, `paginate`, `simplePaginate` |
| Writes | Single-row and bulk `insert`, `insertOrIgnore`, `insertGetId`, simple `update`, `increment` / `decrement`, `delete` with or without a key, all without write modifiers |
| Eloquent metadata | Source-declared table and primary key, inherited defaults, selected unambiguous English table conventions, standard authentication model ancestry |
| Eloquent scopes | Traditional source-declared `scopeX` methods with supported mutations; standard `SoftDeletes` reads, `withTrashed()`, `onlyTrashed()` and `withoutTrashed()` |

Raw fragments with an unknown binding array bind one open value per `?` in the
fragment. `paginate` lists both the count and the page statement; the page
offset is external input, since Laravel reads the page from the request. A
`chunk` or `each` window is a gap built by the loop. An Eloquent `with` keeps
the main statement and adds one open statement for the eager load it issues.

An explicit `$table` is required when conventional pluralization is not known.
Local scopes with early returns, replacement builders or boolean regrouping
remain incomplete. Eloquent updates currently require `$timestamps = false`;
soft-delete writes and model persistence operations are incomplete.

## Incomplete operations

Unknown builder methods and macros, subqueries, unions, JSON selectors, cursor
pagination, relation queries, the statements an eager load issues, custom
builders, global scopes, model boot hooks, timestamped updates, locks and
persistence calls such as `create`, `save`, `firstOrCreate` and
`updateOrCreate` are not yet reconstructed. Recognized execution calls remain in
the catalog with incomplete evidence. An unresolved relation receiver may be
reported as an `unmatched` sink rather than as a confirmed Laravel statement.

Implicit `$with` / `$withCount` loads, model attributes, unknown traits and
unresolved or mutable model metadata also keep the result open. Direct mutation
of builder properties and PHP reference rebinding are not interpreted as fluent
operations. This prevents an unmodelled condition from silently disappearing
from an otherwise exact statement.

These gaps use the ordinary catalog resolutions and findings; they are not
reported as successful support for the operation. Check `searchClosed` as well
as `exact` when consuming results.
