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

## Supported operations

Support applies to the standard overloads below with resolvable identifiers and
complete array shapes. Named or unpacked arguments are currently incomplete.

| Area | Supported forms |
| --- | --- |
| Query creation | `DB::table`, `DB::connection(...)->table`, Illuminate connection `table`, model `query` and static builder calls |
| Projection | `select`, `addSelect`, `selectRaw`, terminal column arguments, `DB::raw` expressions |
| Predicates | Scalar `where` / `orWhere`, null tests, `whereIn` / `whereNotIn`, between tests, `whereColumn`, `whereRaw`, nested predicate closures and arrow functions |
| Clauses | Simple inner/left/right column joins, `groupBy`, `havingRaw`, ordering including raw SQL, non-negative literal limits/offsets, argument-free `distinct` |
| Reads | `get`, `all`, `first`, `firstOrFail`, scalar `find`, `pluck`, `count`, `sum`, `avg`, `min`, `max`, `exists`, `doesntExist` |
| Writes | Single-row and bulk `insert`, `insertOrIgnore`, simple `update` and `delete` without write modifiers |
| Eloquent metadata | Source-declared table and primary key, inherited defaults, selected unambiguous English table conventions, standard authentication model ancestry |
| Eloquent scopes | Traditional source-declared `scopeX` methods with supported mutations; standard `SoftDeletes` reads, `withTrashed()` and `onlyTrashed()` |

An explicit `$table` is required when conventional pluralization is not known.
Local scopes with early returns, replacement builders or boolean regrouping
remain incomplete. Eloquent updates currently require `$timestamps = false`;
soft-delete writes and model persistence operations are incomplete.

A nullable or untyped comparison value can change `where('column', $value)`
from `= ?` to `is null`. If the value cannot be resolved, the SQL remains open
instead of assuming a placeholder. In contrast, values in a complete `whereIn`
array can remain unknown while the placeholder structure is known.

## Incomplete operations

Unknown builder methods and macros, subqueries, unions, conditional builders,
JSON selectors, pagination, relation queries, eager loading, custom builders,
global scopes, model boot hooks, timestamped updates and persistence calls such
as `create`, `save`, `firstOrCreate` and `updateOrCreate` are not yet reconstructed.
Recognized execution calls remain in the catalog with incomplete evidence.
An unresolved relation receiver may be reported as an `unmatched` sink rather
than as a confirmed Laravel statement. Multi-query operations are not expanded
into their individual SQL statements.

Implicit `$with` / `$withCount` loads, model attributes, unknown traits and
unresolved or mutable model metadata also keep the result open. Direct mutation
of builder properties and PHP reference rebinding are not interpreted as fluent
operations. This prevents an unmodelled condition from silently disappearing
from an otherwise exact statement.

These gaps use the ordinary catalog resolutions and findings; they are not
reported as successful support for the operation. Check `searchClosed` as well
as `exact` when consuming results.
