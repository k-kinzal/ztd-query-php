# The catalog file format

`--reporter json` writes two files into the output directory:

| File | What it is |
|------|------------|
| `catalog.json` | The catalog |
| `catalog-schema.json` | The JSON Schema the catalog declares itself against |

The schema is written beside the document rather than pointed at over the
network, so a catalog that has been committed, copied or archived stays readable
without asking anything else for the shape of it. The same schema ships in the
package at `resources/catalog-schema.json`, and a rendered document is validated
against it by the test suite, so the two cannot drift apart.

The document is ordered by where each statement is issued and holds nothing that
changes between runs of the same source — no timestamps, no paths outside the
analysis root, no identifiers derived from line numbers. Two runs of the same
source produce the same bytes, so the artifact can be committed and the diff of a
pull request read as the change in the SQL an application issues.

```json
{
    "$schema": "catalog-schema.json",
    "version": 1,
    "summary": {
        "statements": 1,
        "resolved": 1,
        "undetermined": 0,
        "findings": 0
    },
    "statements": [
        {
            "id": "c80ddc2f2643",
            "kind": "select",
            "sql": "SELECT id FROM users WHERE id = ?",
            "exact": true,
            "resolution": "resolved",
            "searchClosed": true,
            "correlated": true,
            "tables": ["users"],
            "site": {
                "file": "src/UserRepository.php",
                "line": 18,
                "function": "App\\UserRepository::byId",
                "sink": "pdo.prepare"
            },
            "through": [],
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
| `version` | The format version. A reader that does not know this number should not guess. |
| `summary` | How many statements were found, how many resolved fully, how many did not, and how many findings were reported. |
| `statements[].id` | The identifier the statement keeps across runs: a hash of the file, the enclosing function, the database call and the statement shape. Deliberately not of the line number, so editing unrelated lines does not renumber the catalog. |
| `statements[].kind` | What the statement does: `select`, `insert`, `update`, `delete`, `replace`, `merge`, `truncate`, `create`, `alter`, `drop`, `call`, `show`, `explain`, `transaction`, `other` or `unknown`. |
| `statements[].sql` | The statement text. Every value the analyzer could not pin down is written as `{$}`. |
| `statements[].exact` | Whether the text holds no gaps. |
| `statements[].resolution` | How far the analyzer got and why it got no further: `resolved`, `external-input`, `incomplete-model`, `incomplete` or `not-analyzed`. |
| `statements[].searchClosed` | Whether the analyzer closed every dependency it set out to follow: the resolution is `resolved` or `external-input`, and no bound on loop passes, callers or ways in cut the search short. When false, the statements listed for this call site may not be all of them. |
| `statements[].correlated` | Whether the alternatives listed are ones the code can reach. When false, the text was assembled from parts that vary independently, so some combinations may be unreachable. |
| `statements[].tables` | The tables the statement names, in order and without repeats. |
| `statements[].site` | The file and line, the enclosing function, and which database call was matched. A `sink` of `unmatched` means the call is written the way a database call is written but what it is called on could not be worked out. A call with a `not-analyzed` resolution is one nothing was read from, which is how a gap in the analysis is told apart from a statement whose text did not resolve. |
| `statements[].through` | The calls that were followed to reach this reading, outermost first. Empty when the statement was read from the body it is written in. |
| `statements[].placeholders[].value` | What the parameter is bound to, or `null` when no binding was found. `exhaustive` says whether `values` is all of them; when it is false, only `type` is a statement about the value. |
| `statements[].findings` | What is worth reporting: `unresolved-sql`, `dynamic-sql`, `external-input`, `placeholder-count-mismatch`, `analysis-incomplete` or `call-not-analyzed`. |
| `problems` | Files that could not be analyzed at all, with the reason. |

## Reading it

A statement is trustworthy as a complete answer for its call site when
`searchClosed` and `correlated` are both true. Otherwise:

- `searchClosed: false` — the listed statements are a lower bound. There may be
  more, and the accompanying `analysis-incomplete` finding says what stopped the
  search.
- `correlated: false` — the listed statements are an upper bound. Some
  combinations may be unreachable.

## Reading a diff

Three changes are worth looking for in a pull request:

- a statement **added or removed** — the application issues different SQL;
- `resolution` moving away from `resolved` — a statement that used to be fixed is
  now assembled from something the analyzer cannot follow;
- a finding appearing, especially `external-input`.

A statement whose `id` is unchanged but whose `site.line` moved is the same
statement in a file that was edited above it.

## The HTML report

`--reporter html` writes a site rather than a page, because a catalog of a real
application runs to thousands of statements and neither one document nor one
scroll holds them:

| File | What it is |
|------|------------|
| `index.html` | The overview: the counts, how far the analysis got, what the statements do, what was reported, the tables and every file |
| `statements/page-N.html` | The statements themselves, split across pages by the file they are written in. A file's statements are never split, so a file with more of them than a page holds gets a page to itself |
| `tables.html` | Every table the statements name, with what reads it and what writes it |
| `findings.html` | Every finding, under the rule that reported it |
| `assets/report.css`, `assets/report.js` | The stylesheet and the script, written once beside the pages rather than into each of them |
| `assets/search-index.js` | Every statement and the page it is on, so one statement stays findable once it is no longer all on one screen |

The pages are read from the file system as readily as from a server: the search
index is a script rather than data fetched at runtime, and nothing is loaded
over the network.

A gap is rendered as a marked `{$}` that says, when pointed at, where the value
filling it comes from — and a call that no statement was read from is not
dressed up as a statement at all, but shown as the call it is, with the reason
the analysis has nothing to say about it.
