# Source traceability for the Bison reader

This application traces the **Symbols** and **Syntax of Grammar Rules** sections
of the official HTML manual to the existing Behat suite. The scope is the direct
prose/list items in Symbols and direct prose/code examples in Rules Syntax; it is
not the entire Bison manual or every feature in this package.

The referenced HTML pages identify themselves as Bison **3.8.1 (10 September 2021)**. The existing
BDD features use the **3.8.2** release's Texinfo manual and already explain that the
published HTML edition is 3.8.1. These definitions explicitly cite the HTML edition;
keep version differences visible when expanding the scope. Do not silently retitle
the HTML edition as 3.8.2. Full upstream documents are not distributed here.
The first source check downloads each public URL, verifies its SHA-256 and writes
an ignored `.requirements-cache/` file. Later checks verify and reuse the cache.
An empty cache requires network access, including on CI. Do not commit cached HTML.
The retrieval URLs use GNU's HTTP endpoint because its HTTPS endpoint times out
from local and CI runners. The SHA-256 pins were verified against the previously
retrieved HTTPS bytes; every normal fetch must match those exact digests.

From this package directory in the monorepo:

```console
composer install
composer install --working-dir=../requirements
php ../requirements/bin/requirements lint
php ../requirements/bin/requirements check
php ../requirements/bin/requirements coverage
php ../requirements/bin/requirements spec
php ../requirements/bin/requirements spec --no-test --status unsupported
php ../requirements/bin/requirements coverage --json
```

The initial scope has **29 units: 17 supported, 3 reasoned unsupported and 9
uncovered (68.97% accounted)**. Unsupported units describe generated-parser,
scanner or compilation behavior outside this grammar-file reader. They have
explicit reasons and are never counted as verified implementations. Uncovered
units remain visible instead of being labeled unsupported merely to raise the
percentage.

There are **13 supported specifications** linked to **27 existing scenarios**,
plus three unsupported specifications. Three name specifications share one
optional requirement to demonstrate one-to-many derivation. Other specifications
cite source units directly. `spec` shows passed/linked test targets for each
record, while `spec --no-test` lists the same records with `-/N` counts without
executing Behat. The optional upstream requirement is shown as `not-applicable`. Related-rule links illustrate cross-specification
context. Production code and feature files are unchanged.

The unit is a full selected paragraph, list item or code example. Some paragraphs
contain both reader syntax and generator details: coverage means that this source
unit has an explicit interpretation, not that every clause is implemented or
semantically proven. Review the complete quote, statement and linked tests together.
In particular, the string-token quote includes generator conventions beyond what
the linked reader scenarios assert. A clause-by-clause audit requires more granular
source units and specifications.

The `Requirements` job in [the package CI](../../../.github/workflows/bison-parser.yml)
enforces the current overall/per-source floors and 100% accounted coverage of
new or changed units compared with the baseline from the PR's base branch. It also
checks that the committed fingerprint-only baseline equals the current analysis, then runs the traced
Behat scenarios. The same workflow runs on reader or requirements-tool changes;
its test matrix also runs the complete BDD suite. Update the baseline:

```console
php ../requirements/bin/requirements coverage --write-baseline requirements-baseline.json
```

Use `check --live` to compare against the public URLs. To update a source, download
the resource into the ignored cache, update its SHA-256 and review quotation/scope changes before
regenerating the baseline. Source changes or unavailability fail checks.
