# Reports

| Reporter | Writes | Use |
|----------|--------|-----|
| `text` | `catalog.txt` | Reading in a terminal. The default on standard output. |
| `json` | `catalog.json`, `catalog-schema.json` | Committing and diffing. The default with `--output`. |
| `html` | `index.html` and the pages it links to | Browsing and sharing. |

Select one with `--reporter`. `json` and `html` write several files, so give them `--output`; on standard output they print only their main file.

## Text

One block per statement, followed by a summary:

```
src/Search.php:17  SELECT  742968908c6b
  in App\Search::run via pdo.query
  SELECT id FROM users WHERE name = '{$}'
  external-input
  [MEDIUM] dynamic-sql 1 value(s) are spliced into the statement text rather than bound.
  [HIGH] external-input A value from external input reaches the statement text.
```

The lines are the call site with the statement kind and ID, the enclosing function and the sink, the SQL, the [resolution](analysis.md#resolution), the bound values, and the [findings](analysis.md#findings).

## JSON

`catalog.json` is validated by `catalog-schema.json`, which is written next to it and also ships in the package as `resources/catalog-schema.json`. The document contains no timestamps, absolute paths or line-based identifiers, so the same source always produces the same bytes.

```json
{
    "$schema": "catalog-schema.json",
    "version": 2,
    "analysis": {
        "conditions": "not-evaluated",
        "reachability": "not-assessed"
    },
    "summary": {
        "statements": 1,
        "resolved": 1,
        "undetermined": 0,
        "findings": 0
    },
    "statements": [
        {
            "id": "eea410b914be",
            "kind": "select",
            "sql": "SELECT id FROM users WHERE status = :status",
            "exact": true,
            "resolution": "resolved",
            "searchClosed": true,
            "correlated": true,
            "tables": ["users"],
            "site": {
                "file": "src/UserRepository.php",
                "line": 23,
                "function": "App\\UserRepository::findByStatus",
                "sink": "pdo.prepare"
            },
            "through": ["App\\UserRepository::findByStatus"],
            "placeholders": [
                {
                    "token": ":status",
                    "position": 0,
                    "name": "status",
                    "value": {
                        "type": "string",
                        "values": ["active", "banned"],
                        "exhaustive": true,
                        "origins": []
                    }
                }
            ],
            "findings": []
        }
    ],
    "problems": []
}
```

| Field | Description |
|-------|-------------|
| `version` | The format version, `2`. Do not read a version you do not know. |
| `analysis` | Always `conditions: not-evaluated` and `reachability: not-assessed`. See [analysis](analysis.md#statements). |
| `summary` | Counts of statements, of statements without gaps (`resolved`) and with gaps (`undetermined`), and of findings. |
| `statements[].id` | A stable identifier: a hash of the file, the enclosing function, the sink and the statement shape. It does not change when unrelated lines move. |
| `statements[].kind` | `select`, `insert`, `update`, `delete`, `replace`, `merge`, `truncate`, `create`, `alter`, `drop`, `call`, `show`, `explain`, `transaction`, `other` or `unknown`. |
| `statements[].sql` | The SQL text, with `{$}` for each unknown value. |
| `statements[].exact`, `resolution`, `searchClosed`, `correlated` | How complete the statement is. See [resolution](analysis.md#resolution). |
| `statements[].tables` | The tables named, in order and without repeats. A partly known name contains `{$}`; an unknown name is left out. |
| `statements[].site` | The `file` relative to the root, the `line`, the enclosing `function` (`Class::method`, a function name, or `{main}`), and the `sink` ID. |
| `statements[].through` | The functions followed to reach this reading, from the body the search started in to the one holding the call. |
| `statements[].placeholders[]` | Each placeholder in order: its `token`, zero-based `position`, `name` for named placeholders, and bound `value`, or `null` when no binding was found. |
| `…value.type` | The PHP type of the bound value. |
| `…value.values`, `exhaustive` | The possible values. When `exhaustive` is false, only `type` is known. |
| `…value.origins` | Where the unknown parts come from. See [origins](analysis.md#where-unknown-values-come-from). |
| `statements[].findings[]` | Each finding's `rule`, `severity` and `message`. |
| `problems[]` | Files that could not be analyzed, with `file` and `message`. |

### Reading a diff

In a pull request, look for:

- statements added or removed;
- `resolution` changing from `resolved`: the statement now depends on something the analyzer cannot follow;
- new findings, especially `external-input`;
- `searchClosed` becoming `false`: the call site may now send statements the catalog does not list.

A statement with the same `id` and a different `site.line` is unchanged; only the code above it moved.

## HTML

`--reporter html --output DIR` writes a static site. Open `DIR/index.html` in a browser; it works from the file system, without a server or network access.

| Page | Contents |
|------|----------|
| `index.html` | Overview: the most used tables, classes and files, findings by rule, and how far the analysis got. Every count links to its statements. |
| `statements.html` | Every statement, filterable by kind, resolution, severity and text. |
| `tables.html`, `tables/` | Every table, and a page per table with the functions that use it, related tables, and its reads, writes and schema changes. |
| `namespaces.html`, `classes/` | Every namespace, and a page per class with its statements by method. |
| `files.html`, `files/` | Every file by directory, and a page per file with its statements by function. |
| `findings.html` | The functions with the most findings, and every finding by rule. |
| `statements/` | A page per statement: the formatted SQL, the SQL as written, the call site and path, tables, bound values, findings, related statements, and the PHP source around the call. |
| `assets/` | The stylesheet, scripts and search index. |

Every page has a search box that finds statements by SQL, table, function or file. SQL is formatted with [sql-formatter](../../sql-formatter/) when a MySQL, PostgreSQL or SQLite grammar accepts it, and shown as written otherwise. In the HTML, a gap shows the PHP variable it comes from, such as `{$sql}`, when that is known.

The report embeds the source code around each database call and the full text of each analyzed file. Share it only where you would share the source.
