# WordPress

The `wordpress` extension catalogs SQL sent through WordPress's `wpdb`, including SQL built with `wpdb::prepare()`. Enable it with `--extension`:

```console
vendor/bin/sql-catalog --extension wordpress wp-content/plugins/my-plugin/
```

```php
function my_plugin_find_post(int $id)
{
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id));
}
```

```
SELECT * FROM {$} WHERE ID = %d
incomplete-model; search did not close
[MEDIUM] dynamic-sql 1 value(s) are spliced into the statement text rather than bound.
```

The table name comes from `$wpdb->posts`, which WordPress sets at runtime.

## Calls

| Sink ID | Call | Reads |
|---------|------|-------|
| `wordpress.query` | `wpdb::query($sql)` | The SQL. |
| `wordpress.get_results` | `wpdb::get_results($sql)` | The SQL. |
| `wordpress.get_row` | `wpdb::get_row($sql)` | The SQL. |
| `wordpress.get_col` | `wpdb::get_col($sql)` | The SQL. |
| `wordpress.get_var` | `wpdb::get_var($sql)` | The SQL. |
| `wordpress.get_col_info` | `wpdb::get_col_info($sql)` | The SQL. |
| `wordpress.prepare` | `wpdb::prepare($query, ...$args)` | Not reported on its own. It returns its query, so the call that runs the result reports it. |

The `get_*` methods report the kind `select` when the SQL does not say otherwise.

## `wpdb::prepare()`

The statement is reported with the query given to `prepare()`, with `%d`, `%s`, `%f` and `%i` left as written. Values passed to `prepare()` are escaped by WordPress, so they do not produce `dynamic-sql` or `external-input` findings, and they are not listed as bound values.

A value concatenated into the query before `prepare()` is still spliced into the SQL, and is reported like any other.

## The `$wpdb` global

The extension declares that the global `$wpdb` is a `wpdb`, so calls after `global $wpdb;` are recognised without a type. Other variables must be typed, for example as a parameter `wpdb $db`, or declared global with an `@global wpdb` tag.

Any function or method call between `global $wpdb;` and the database call might reassign the global, so after it `$wpdb` is unknown and the database call is reported with the sink `unmatched`. This includes `do_action()`, `$wpdb->insert()` and functions in the analyzed source. The exceptions are the `wpdb` methods in the table above and PHP functions with a [function model](../configuration.md#function-models), such as `sprintf()` and `implode()`.

```php
global $wpdb;
do_action('before_cleanup');
$wpdb->query("DELETE FROM {$wpdb->prefix}logs"); // reported as unmatched
```

## Limits

- Table names built from `$wpdb->prefix`, `$wpdb->posts` and similar properties are set at runtime, so they appear as `{$}`, the statement is `incomplete-model`, and it has a `dynamic-sql` finding.
- `insert()`, `update()`, `replace()` and `delete()` build their SQL from arguments and are not catalogued.
- SQL returned by `apply_filters()` and other hooks is unknown.
- A placeholder list built with `array_fill()` of `'%d'` or `'%s'` is unknown. Only `'?'` is modelled; see [function models](../configuration.md#function-models).
