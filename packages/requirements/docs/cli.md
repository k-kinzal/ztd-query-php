# Command line interface

Symfony Console provides command discovery, per-command help, terminal tables,
color detection and shell completion. Run `requirements --help` for an overview
or `requirements coverage --help` for the coverage options. Help works without a
configuration file. `requirements spec` displays specification and requirement
records and verifies linked tests. Add `--no-test` to browse without execution.

```console
requirements --help
requirements coverage --help
requirements --config project/requirements.yaml coverage
requirements spec --no-test --label grammar
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
| `spec` | Browse specifications/requirements, provenance, rationale and labels; verify linked tests unless `--no-test` is set |
| `lint` | Validate documents, IDs, EARS form, extension registration and the cross-file graph without fetching sources or running tests |
| `format` | Normalize YAML or experimental Markdown formatting; `--check` detects drift without writing |

Every command accepts `--config FILE` (default `requirements.yaml`) and `--json`.
`spec` accepts `--id`, `--label`, `--category`, `--source`, `--status`,
`--kind`, `--origin` and `--without-source`. Filters combine with AND and use exact
values. `--source` selects the definition's source ID; inherited source links are
available in the requirement graph. An empty `spec` selection fails, including
with `--no-test`. Requirements remain visible with `not-applicable` results and
are never executed. Use `--kind specification` to display specifications only.

The former record-listing command is now `spec --no-test`. Symfony's built-in
`list` command only displays available CLI commands, like the application overview.

`check` and `coverage` accept `--live` to fetch source URIs instead of snapshots.
Default mode uses verified local snapshots where configured, otherwise the URI.
A missing snapshot is downloaded, verified and cached automatically. Source
adapters cache content only for the current command. `format` normalizes the document and
removes YAML comments; keep explanatory material in `reason`, `design` or `metadata`.

Exit codes: **0** success, **1** failed check, gate, verification or format check,
**2** invalid configuration, options or an unrecoverable error. Source failures
appear in reports and fail checks; they never count as full coverage.


## Specification results

```console
requirements spec
requirements spec --no-test
requirements spec --no-test --without-source --label strictness
requirements spec --status unsupported
requirements spec --kind requirement --json
```

The **Tests** column counts **passed targets / linked targets for that record**.
A target is a distinct configured runner name + test reference (`Class::method`
or `file.feature:line`). Repeated references within one record count once. A shared
target contributes to each linked record but executes only once per invocation.
A target passes only when its whole selection passes and reports at least one
executed test case. Data-provider cases and scenario-outline examples do not
inflate the linked-target count: a method with 33 cases is one target.

| Situation | Result | Tests | Exit code when selected alone |
| --- | --- | --- | --- |
| Three linked targets all pass | `passed` | `3/3` | 0 |
| One of three targets fails, skips or selects no tests | `failed` | `2/3` | 1 |
| Tests intentionally disabled with `--no-test` | `not-run` | `-/3` | 0 |
| Supported specification has no tests | `unverified` | `0/0` | 1 |
| No tests linked and `--no-test` | `not-run` | `-/0` | 0 |
| Unsupported specification has three targets | `unsupported` | `-/3` | 0 |
| Upstream requirement | `not-applicable` | `-/0` | 0 |

`--no-test` loads and validates definitions but does not fetch sources or invoke
runner execution. It does not reuse past test results; `-` means unknown, never a
cached pass. A successful exit in this mode means the selected records were
displayed, not that their tests passed. Unsupported records retain their reason.

`--json` uses the same `specifications` map in both modes, keyed by record ID;
requirements are included too. Each row includes statement, kind, source/origin,
labels, category, reason, requirement/related links, design, metadata and
`test_references`. `support` is the declared supported/unsupported disposition;
`status` is the verification result. The numeric fields are:

- `passed_targets`: successful linked targets, or `null` when not run/applicable;
- `total_targets`: distinct linked targets, available without execution;
- `tests`: reported case count from executed targets, including failed or skipped
  cases; zero when nothing executed. This is not a passed count or a project total.

The top-level `no_test` records the requested mode. `passed` expresses command
success: in execution mode all selected supported specifications must verify; in
browsing mode a nonempty valid selection succeeds. Neither field asserts that an
upstream requirement or an unsupported decision has been implemented.


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
requirements coverage --snapshot /tmp/base-snapshot.json
requirements spec
```

A coverage snapshot is not a list of accepted failures. It records every unit in
the declared scope, and for each unit the semantic fingerprint of the records
claiming it, so a later run can tell which units are new or changed. Write one with
`requirements coverage --write-snapshot requirements-snapshot.json`. It contains
only unit keys and fingerprints, never source text, and it does not shrink as
coverage improves. Commit it for comparison by later changes. During
pull-request CI extract the snapshot **from the trusted base revision**, not from
the pull request's updated file:

```sh
git show "origin/$GITHUB_BASE_REF:packages/example/requirements-snapshot.json" > /tmp/base-snapshot.json
requirements coverage --snapshot /tmp/base-snapshot.json --min-diff-coverage 100
```

Fetch the base ref first. On the initial adoption PR no base snapshot exists, so use
the total gate and review the initial scope. `--min-coverage` and
`--min-diff-coverage` override configured thresholds. A configured positive diff
threshold requires `--snapshot`. Units whose fingerprint equals the snapshot are
outside the differential denominator; when nothing changed the `diff` summary
reports `percentage: null` and passes. New units and units whose text or claiming
records changed form the differential denominator. This includes changed
statements, status, reason, requirements and test references. Units the snapshot
lists but the current scope no longer contains are reported under `removed` and
fail unless the scope reduction was reviewed and `--allow-removed` is explicitly
supplied.

`spec` deduplicates runner/target pairs and runs each selection in a subprocess
with a timeout and fresh JUnit directory. Passing requires exit 0, at least one
reported test and no skipped, pending, undefined, failed or errored tests. All tests
linked to a specification must pass. A supported specification with no tests is
unverified and fails. Commands are argument arrays, not shell strings.


See [document formats](format.md), [lint rules](lint.md) and
[traceability](traceability.md) and [source/test extensions](extensions.md).
