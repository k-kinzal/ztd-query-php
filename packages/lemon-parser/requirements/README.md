# Source traceability for the Lemon reader

This application links the prose of **4.0 Input File Syntax**, **4.1 Terminals and
Nonterminals** and **4.2 Grammar Rules** from SQLite **3.47.2**'s Lemon manual to the
existing Behat suite. The versioned HTML resource is pinned by SHA-256 and downloaded on demand. The declared
scope is its fifteen body paragraphs 36–50, before 4.3 Precedence Rules, and the
five code examples of 4.2 Grammar Rules, which the linked scenarios parse word for
word. The rest of the manual and other existing features are outside this scope.
The explicit CSS selector list keeps these unit boundaries reviewable against the
versioned source. Full upstream documents are not committed.
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

The source coverage is **20/20 units (100% accounted)**: sixteen supported and
four reasoned unsupported. The initial scope of fifteen paragraphs was 10/15
(66.67%) with five uncovered paragraphs; each of those has since been read for
what the reader does with it, and none was labeled unsupported to raise the
percentage. Two unsupported specifications explain why unused-alias validation
and execution of destructors belong to code generation or the generated parser,
rather than this grammar-file reader; the example rule whose alias `C` is unused
is quoted as evidence of the first. A paragraph may contain several clauses; this
is coverage of reviewed source units, not a proof that every semantic clause is
implemented.

The formerly uncovered paragraphs are specified by what the reader records:

- The opening paragraph of 4.0, which says the file defines the grammar and also
  carries the additional information Lemon requires, became `LEMON-INPUT-005`: the
  tree holds both the rules and the declarations of a file. It is linked to the
  scenarios that read declarations before, among and after rules.
- The sentence naming the one nonterminal and five terminals of the first example
  became `LEMON-RULE-004`, with a new scenario that parses that example and lists
  its nonterminals and terminals as the first letter of each symbol tells them apart.
- The paragraphs contrasting yacc's `$$`/`$1` positions with Lemon's symbolic names,
  including "But in Lemon, the same rule becomes the following:", became
  `LEMON-RULE-005` (an action is linked to a symbol by the name in parentheses,
  verified by the existing scenario that parses the manual's own example) and
  `LEMON-RULE-006` (the yacc form is not a Lemon rule, and a `$`-numbered position
  inside Lemon action code is C text that stands for no symbol), each with a new
  scenario. Lemon translates aliases, not `$` positions, so the reader gives them no
  meaning.

**Fourteen supported specifications execute 29 scenarios**, of which three were
added for this scope and the rest already existed. `spec` shows passed/linked test
targets per record; `spec --no-test` displays the same records with `-/N` counts
without executing Behat. Three of the fourteen are deliberate independent reader
decisions: reject a truncated rule, a truncated symbol list and an unclosed
conditional parenthesis. They use `source: null`, `origin: original` and explicit
reasons, with design references and related links. They are listed by
`--without-source` and never increase source coverage. This preserves the
distinction already documented in `reader-decisions.feature`.

The `Requirements` job in [the package CI](../../../.github/workflows/lemon-parser.yml)
checks YAML and source evidence, fingerprint-only baseline reproducibility, the 100% total/source
floor and 100% accounted coverage of changed units against a baseline extracted
from the PR's trusted base branch. It then executes the linked scenarios. The
same workflow runs on reader or requirements-tool changes, and its test matrix
continues to run all BDD scenarios. Parser code is unchanged; the Behat context
gained two steps that list the nonterminals and terminals a file names.

```console
php ../requirements/bin/requirements coverage --write-baseline requirements-baseline.json
php ../requirements/bin/requirements check --live
```

Review source, selector, statement and test changes together before updating the
baseline. The versioned source URL makes a live check reproducible; an existing ignored cache
allows offline checks. An empty cache requires network access, including on CI.
Do not commit cached HTML. Baselines contain only unit keys and fingerprints.
