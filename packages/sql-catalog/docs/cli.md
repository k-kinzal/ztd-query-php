# Command Line

```console
vendor/bin/sql-catalog [options] <path>...
```

Each path is a PHP file or a directory. Directories are searched recursively for `*.php` files, skipping `vendor`, `node_modules`, `.git` and `build`. Paths can also come from the [configuration file](configuration.md); paths given on the command line replace them.

Without `--output` the report is written to standard output in the `text` format. With `--output` it is written into that directory in the `json` format, and the command prints the files it wrote.

```console
$ vendor/bin/sql-catalog src/
src/UserRepository.php:23  SELECT  eea410b914be
  in App\UserRepository::findByStatus via pdo.prepare
  SELECT id FROM users WHERE status = :status
  resolved
  :status = 'active'|'banned'

Conditions are not evaluated; runtime reachability is not assessed.
1 statement(s), 1 fully resolved, 0 finding(s), 0 unreadable file(s).
```

## Options

| Option | Description |
|--------|-------------|
| `-o`, `--output=DIR` | Write the report into `DIR` instead of standard output. |
| `-r`, `--reporter=NAME` | `text`, `json` or `html`. Default `text` on standard output, `json` with `--output`. See [reports](format.md). |
| `-c`, `--config=FILE` | Configuration file. Default `.catalog.yaml` in the working directory, when it exists. |
| `-e`, `--extension=NAME` | Database API to recognise: `pdo`, `mysqli`, `doctrine`, `laravel` or `wordpress`. Default `pdo,mysqli`. See [extensions](extensions.md). |
| `--dialect=NAME` | SQL grammar of framework query builders: `mysql`, `pgsql` or `sqlite`. See [Laravel](extensions/laravel.md). |
| `--exclude=PATTERN` | Skip source files whose reported path matches the pattern or lies under it. |
| `--root=DIR` | Directory that reported paths are relative to. Default the working directory. |
| `--fail-on=LEVEL` | Exit with `1` when a reported statement has a finding at `LEVEL` or above. |
| `--list-extensions` | List the extensions and exit. |
| `--list-reporters` | List the reporters and exit. |
| `-h`, `--help` | Show the help and exit. |

Options marked repeatable in `--help` can be given several times or as a comma-separated list, for example `--kind insert,update`. For other options the last value wins.

Help and the listings work without a configuration file and ignore it.

## Filters

Filters select which statements are reported. They are applied after the analysis, so they do not change what a statement resolves to. Values of one filter are alternatives; different filters must all match.

| Option | Keeps statements |
|--------|------------------|
| `--namespace=NS` | issued in a function or method under the namespace, ignoring case. |
| `--method=NAME` | issued in the function, written as `name`, `Class::method` or a pattern such as `App\Repository\*::find*`. |
| `--path=PATTERN` | written in a file whose reported path matches the pattern or lies under it. |
| `--kind=KIND` | of the kind: `select`, `insert`, `update`, `delete`, `replace`, `merge`, `truncate`, `create`, `alter`, `drop`, `call`, `show`, `explain`, `transaction`, `other` or `unknown`. |
| `--table=NAME` | naming the table, ignoring case. |
| `--sink=ID` | found at the database call, such as `pdo.prepare`. `unmatched` selects calls whose receiver could not be identified. |
| `--severity=LEVEL` | with a finding at `LEVEL` or above: `info`, `low`, `medium` or `high`. |

Patterns use shell wildcards (`*`, `?`, `[...]`) and are matched against paths relative to `--root`.

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | Success. |
| `1` | `--fail-on` is set and a reported statement reaches that severity. |
| `2` | Invalid options or configuration. |
| `3` | A source path cannot be read, the output cannot be written, or the extension or reporter named is unknown. |

Files that fail to parse do not change the exit code. They are listed as unreadable in the report.

## CI

Commit the JSON catalog and regenerate it in every pull request. The file is deterministic, so its diff is the change in the SQL the application issues:

```console
vendor/bin/sql-catalog --output catalog/ src/
git diff --exit-code catalog/
```

To block SQL assembled from request input, fail on high-severity findings:

```console
vendor/bin/sql-catalog --severity high --fail-on high src/
```

See [reading a diff](format.md#reading-a-diff) for what to look for.
