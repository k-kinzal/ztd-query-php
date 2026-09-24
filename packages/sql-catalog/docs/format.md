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
| `statements[].tables` | The tables the statement names, in order and without repeats. A name the analyzer knows only part of — a prefix read from configuration, say — is written with `{$}` in place of the unknown part; a name nothing is known of is not listed. |
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

`--reporter html` writes a site rather than a page. A reader comes to a catalog
to find a statement and decide something about it — which queries a column
rename will break, what a class issues before it is refactored, where SQL is
assembled from values the program does not control — so the site is laid out
as the routes to a statement rather than as one long listing:

| File | What it is |
|------|------------|
| `index.html` | The overview: the routes to a statement, with the most used tables, classes and files on each; what needs attention, by rule and by the functions flagged most; and how far the analysis got, with every count a link to the statements it counts |
| `statements.html` | Every statement, to narrow down on the page by kind, resolution, severity and text. Links from elsewhere in the report arrive here with the narrowing in the query string, as `?kind=delete` or `?resolution=external-input&namespace=App` |
| `tables.html`, `tables/*.html` | Every table the statements name, grouped by schema when any is qualified; and one page per table with the functions that use it, the tables named alongside it, and its statements as writes, reads and schema changes |
| `namespaces.html`, `classes/*.html` | Every namespace with the classes and functions declared in it; and one page per class with its statements method by method |
| `files.html`, `files/*.html` | Every file by directory; and one page per file with its statements function by function |
| `findings.html` | The functions flagged most, then every finding under the rule that reported it |
| `statements/*.html` | One page per statement: the SQL laid out a clause per line, where it is issued and through what, its tables, bound values and findings, and the other statements of the same function and on the same table |
| `assets/document-design-v1.0.0.css`, `assets/document-design-v1.0.0.js`, `assets/document-design-LICENSE.txt` | The design the pages are written in: the unmodified [document-design](https://k-kinzal.github.io/document-design/) doc-ui release, its script, and the notice naming the release, its license and the SHA-256 of each file |
| `assets/report.css`, `assets/report.js` | What the report needs beyond doc-ui — a few rules in doc-ui's tokens, the ranking of a search over statements, and the narrowing of a listing by the facts a page arrives with — written once beside the pages rather than into each of them |
| `assets/search-index.js` | Every statement and its page, so the search box on every page finds a statement by its SQL, table, function or file |

The pages are written in doc-ui, document-design's design system for
documentation and reports, in its `.doc` layout for catalogs and its components:
the sidebar, topbar and breadcrumbs, listing rows and facets, chips and tones,
tables, code, facts, the meter, stats and cards. The stylesheet and script are
the v1.0.0 release, bundled unmodified and pinned to that version — a report is
read long after it is written, and has to look then the way it looked when it
was checked — so no floating version is ever loaded from a CDN. Colour is
doc-ui's: identity tones for what a statement does, state tones for how far the
analysis got and how much attention a finding wants, and both themes follow the
reader's system unless the switch in the topbar says otherwise.

The pages are read from the file system as readily as from a server: the search
index is a script rather than data fetched at runtime, nothing is loaded over
the network, and every page reads without the scripts — they only add
narrowing, sorting, copying, the theme switch and search.

A gap is rendered as a marked `{$}` that says, when pointed at, where the value
filling it comes from — and a call that no statement was read from is not
dressed up as a statement at all, but shown as the call it is, with the reason
the analysis has nothing to say about it.
