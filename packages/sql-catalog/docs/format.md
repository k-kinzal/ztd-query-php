# The catalog file format

`--reporter json` writes `catalog.json`. The document is ordered by where each
statement is issued and holds nothing that changes between runs of the same
source — no timestamps, no paths outside the analysis root, no identifiers
derived from line numbers. Two runs of the same source produce the same bytes, so
the artifact can be committed and the diff of a pull request read as the change
in the SQL an application issues.

```json
{
    "version": 1,
    "summary": {
        "statements": 1,
        "exact": 1,
        "dynamic": 0,
        "findings": 0
    },
    "statements": [
        {
            "id": "c80ddc2f2643",
            "kind": "select",
            "sql": "SELECT id FROM users WHERE id = ?",
            "exact": true,
            "tables": ["users"],
            "site": {
                "file": "src/UserRepository.php",
                "line": 18,
                "function": "App\\UserRepository::byId",
                "sink": "pdo.prepare"
            },
            "placeholders": [
                {
                    "token": "?",
                    "position": 0,
                    "name": null,
                    "value": {
                        "type": "int",
                        "values": [7],
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

## Fields

| Field | Meaning |
|-------|---------|
| `version` | The format version. |
| `summary` | How many statements were found, how many resolved fully, how many did not, and how many findings were reported. |
| `statements[].id` | The identifier the statement keeps across runs: a hash of the file, the enclosing function, the database call and the statement shape. Deliberately not of the line number, so editing unrelated lines does not renumber the catalog. |
| `statements[].kind` | `select`, `insert`, `update`, `delete`, `replace`, `merge`, `truncate`, `create`, `alter`, `drop`, `call`, `show`, `explain`, `transaction`, `other` or `unknown`. |
| `statements[].sql` | The statement text. Every value the analyzer could not resolve is written as `{$}`. |
| `statements[].exact` | Whether the text holds no gaps. |
| `statements[].tables` | The tables the statement names, in order and without repeats. |
| `statements[].site` | The file and line the statement is issued at, the enclosing function, and which database call was matched. |
| `statements[].placeholders[].token` | The parameter as written: `?`, `:name` or `$1`. |
| `statements[].placeholders[].value` | What the parameter is bound to, or `null` when no binding was found. |
| `statements[].placeholders[].value.values` | The values the parameter can take, when they are all known. |
| `statements[].placeholders[].value.exhaustive` | Whether `values` is all of them. When `false`, only `type` is a statement about the value. |
| `statements[].placeholders[].value.origins` | Where the unresolved parts of the value come from. |
| `statements[].findings` | What is worth reporting about the statement. |
| `problems` | Files that could not be analyzed, with the reason. |

## Reading a diff

Three changes are worth looking for in a pull request:

- a statement **added or removed** — the application issues different SQL;
- `exact` going from `true` to `false` — a statement that used to be fixed is now
  assembled from something the analyzer cannot follow;
- a finding appearing, especially `external-input`.

A statement whose `id` is unchanged but whose `site.line` moved is the same
statement in a file that was edited above it.
