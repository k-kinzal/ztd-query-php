# Command line interface

Symfony Console provides command discovery, per-command help, terminal tables,
color detection and shell completion. Run `requirements --help` for an overview
or `requirements coverage --help` for the coverage options. Help works without a
configuration file. `requirements list` lists specification records.

```console
requirements --help
requirements coverage --help
requirements --config project/requirements.yaml coverage
requirements list --label grammar
requirements spec --json
```

Human-readable reports use tables and concise status messages. `--json` writes the
complete machine-readable report to stdout without styling; execution errors use
the same JSON channel with exit code 2. Human-readable execution errors go to stderr.
`--no-ansi`, `--ansi` and `--quiet` control terminal output. Global options can
precede or follow the command. `requirements completion --help` explains shell
completion installation.

## Commands

| Command | Result |
| --- | --- |
| `check` | Verify full quoted units against the selected source scope |
| `coverage` | Report global and per-source accounted/supported/unsupported/uncovered units and enforce gates |
| `spec` | Execute linked tests and report each specification as passed, failed, unverified or unsupported |
| `list` | List specifications/requirements, provenance, rationale, labels and references |
| `lint` | Validate documents, IDs, EARS form, extension registration and the cross-file graph without fetching sources or running tests |
| `format` | Normalize YAML or experimental Markdown formatting; `--check` detects drift without writing |

Every command accepts `--config FILE` (default `requirements.yaml`) and `--json`.
`list` and `spec` accept `--id`, `--label`, `--category`, `--source`, `--status`,
`--kind`, `--origin` and `--without-source`. Filters combine with AND and use exact
values. `--source` selects the definition's source ID; inherited source links are
available in the requirement graph. An empty `spec` selection fails.

`check` and `coverage` accept `--live` to fetch source URIs instead of snapshots.
Default mode uses verified local snapshots where configured, otherwise the URI.
A missing snapshot is downloaded, verified and cached automatically. Source
adapters cache content only for the current command. `format` normalizes the document and
removes YAML comments; keep explanatory material in `reason`, `design` or `metadata`.

Exit codes: **0** success, **1** failed check, gate, verification or format check,
**2** invalid configuration, options or an unrecoverable error. Source failures
appear in reports and fail checks; they never count as full coverage.


## CI and differential gates

```yaml
coverage:
  minimum: 80
  sources:
    grammar-manual: 90
  diff_minimum: 100
```

```console
requirements lint
requirements format --check
requirements check
requirements coverage --baseline /tmp/base-coverage.json
requirements spec
```

Generate a baseline using
`requirements coverage --write-baseline requirements-baseline.json`. The baseline contains only unit keys and fingerprints, never source text. Commit it
for comparison by later changes. During pull-request CI extract the baseline
**from the trusted base revision**, not from the pull request's updated file:

```sh
git show "origin/$GITHUB_BASE_REF:packages/example/requirements-baseline.json" > /tmp/base-coverage.json
requirements coverage --baseline /tmp/base-coverage.json --min-diff-coverage 100
```

Fetch the base ref first. On the initial adoption PR no base report exists, so use
the total gate and review the initial scope. `--min-coverage` and
`--min-diff-coverage` override configured thresholds. A configured positive diff
threshold requires a baseline. Unchanged units have no differential denominator
(`percentage: null`) and pass. New/changed unit text or claiming records form the
differential denominator. This includes changed statements, status, reason,
requirements and test references. Removed units are listed and fail unless the
scope reduction was reviewed and `--allow-removed` is explicitly supplied.

`spec` deduplicates runner/target pairs and runs each selection in a subprocess
with a timeout and fresh JUnit directory. Passing requires exit 0, at least one
reported test and no skipped, pending, undefined, failed or errored tests. All tests
linked to a specification must pass. A supported specification with no tests is
unverified and fails. Commands are argument arrays, not shell strings.


See [document formats](format.md), [lint rules](lint.md) and
[traceability](traceability.md) and [source/test extensions](extensions.md).
