# Source traceability for the Lemon reader

This application links the prose of **4.0 Input File Syntax**, **4.1 Terminals and
Nonterminals** and **4.2 Grammar Rules** from SQLite **3.47.2**'s Lemon manual to the
existing Behat suite. The versioned HTML resource is pinned by SHA-256 and downloaded on demand. The declared
scope is its fifteen body paragraphs 36–50, before 4.3 Precedence Rules. Code
examples, the rest of the manual and other existing features are outside this
initial scope. The explicit CSS selector list keeps these paragraph boundaries
reviewable against the versioned source. Full upstream documents are not committed.
The first check downloads the manual, verifies its digest and writes an ignored
`.requirements-cache/` file; later checks verify and reuse those bytes.

From this package directory in the monorepo:

```console
composer install
composer install --working-dir=../requirements
php ../requirements/bin/requirements lint
php ../requirements/bin/requirements check
php ../requirements/bin/requirements coverage
php ../requirements/bin/requirements spec
php ../requirements/bin/requirements spec --no-test --without-source
php ../requirements/bin/requirements spec --no-test --status unsupported
php ../requirements/bin/requirements coverage --json
```

The initial source coverage is **10/15 units (66.67% accounted)**: seven supported,
three reasoned unsupported, and five uncovered. Two unsupported specifications
explain why unused-alias validation and execution of destructors belong to code
generation or the generated parser, rather than this grammar-file reader.
Uncovered paragraphs remain visible and are not labeled unsupported to increase
the percentage. A paragraph may contain several clauses; this is coverage of
reviewed source units, not a proof that every semantic clause is implemented.

**Ten supported specifications execute 25 existing scenarios.** `spec` shows
passed/linked test targets per record; `spec --no-test` displays the same records
with `-/N` counts without executing Behat. Three of them
are deliberate independent reader decisions: reject a truncated rule, a truncated
symbol list and an unclosed conditional parenthesis. They use `source: null`,
`origin: original` and explicit reasons, with design references and related links.
They are listed by `--without-source` and never increase source coverage. This
preserves the distinction already documented in `reader-decisions.feature`.

The `Requirements` job in [the package CI](../../../.github/workflows/lemon-parser.yml)
checks YAML and source evidence, fingerprint-only baseline reproducibility, the 66% total/source
floor and 100% accounted coverage of changed units against a baseline extracted
from the PR's trusted base branch. It then executes the linked scenarios. The
same workflow runs on reader or requirements-tool changes, and its test matrix
continues to run all BDD scenarios. Parser code and feature
files are unchanged.

```console
php ../requirements/bin/requirements coverage --write-baseline requirements-baseline.json
php ../requirements/bin/requirements check --live
```

Review source, selector, statement and test changes together before updating the
baseline. The versioned source URL makes a live check reproducible; an existing ignored cache
allows offline checks. An empty cache requires network access, including on CI.
Do not commit cached HTML. Baselines contain only unit keys and fingerprints.
