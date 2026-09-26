# Command Line

```console
vendor/bin/requirements <command> [options]
```

Run `requirements --help` for the command list and `requirements <command> --help` for the options of one command. Help works without a configuration file.

## Commands

| Command | What it does |
|---------|--------------|
| `lint` | Checks the configuration and definition files. See [lint](lint.md). |
| `check` | Checks that every quotation still matches its source. |
| `coverage` | Reports how much of each source scope is covered, and fails below the configured thresholds. |
| `spec` | Lists specifications and requirements and runs their linked tests. |
| `format` | Rewrites definition files into their canonical layout. |

A typical CI job runs them in this order:

```console
requirements lint
requirements format --check
requirements check
requirements coverage
requirements spec --strict
```

## Options

Every command accepts these:

| Option | Description |
|--------|-------------|
| `-c`, `--config=FILE` | Configuration file. Default `requirements.yaml`. |
| `--json` | Write a complete JSON report to stdout instead of tables. |
| `-q`, `--quiet`, `--ansi`, `--no-ansi` | Control terminal output. |

| Command | Option | Description |
|---------|--------|-------------|
| `check`, `coverage` | `--live` | Read sources from their URIs instead of snapshots. |
| `coverage` | `--min-coverage=N` | Override `coverage.minimum`. |
| `coverage` | `--write-snapshot=FILE` | Write a coverage snapshot. See [CI](#ci). |
| `coverage` | `--snapshot=FILE` | Compare with a snapshot of the base revision. |
| `coverage` | `--min-diff-coverage=N` | Override `coverage.diff_minimum`. |
| `coverage` | `--allow-removed` | Accept units that the snapshot has and the current scope does not. |
| `spec` | `--no-test` | List the records without running tests, even with `--all`. |
| `spec` | `--strict` | Fail when no records match or a selected supported specification has no linked tests. Also applies with `--no-test`. |
| `spec` | `--all` | Run manual tests too, within the selected supported specifications. |
| `spec` | `--id`, `--label`, `--category`, `--source`, `--status`, `--kind`, `--origin` | Select records by an exact value. Several filters must all match. |
| `spec` | `--without-source` | Select items without a source. |
| `format` | `--check` | Report files that would change, without writing them. |

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | Success. |
| `1` | A check, threshold, test or `format --check` failed. |
| `2` | Invalid configuration or options, a lint error, or an unrecoverable error. |

## Coverage

A source's `selector` defines its units. A unit is covered when a specification quotes it, directly or through a requirement it implements. Units quoted only by unsupported specifications count as covered too, and the report shows them separately. Items without a source do not change coverage. Coverage says nothing about tests; `spec` checks those.

## Test results

`spec` runs automatic linked tests and shows **passed / linked** tests per record. A data provider or scenario outline counts as one linked test. A supported specification without tests remains `unverified` but does not fail the command by default. This lets you document behavior verified outside this command, such as a separate fuzzing campaign, without inventing a test link.

| Situation | Result | Tests | Default exit | With `--strict` |
|-----------|--------|-------|--------------|-----------------|
| All three linked tests pass | `passed` | `3/3` | `0` | `0` |
| One of three fails, is skipped or runs no test | `failed` | `2/3` | `1` | `1` |
| Supported specification without tests | `unverified` | `0/0` | `0` | `1` |
| Two tests pass; one manual test is deferred | `deferred` | `2/3` | `0` | `0` |
| All three linked tests are manual and deferred | `deferred` | `0/3` | `0` | `0` |
| Unsupported specification | `unsupported` | `-/3` | `0` | `0` |
| Requirement | `not-applicable` | `-/0` | `0` | `0` |
| Run with `--no-test`, with tests linked | `not-run` | `-/3` | `0` | `0` |
| Run with `--no-test`, supported specification without tests | `not-run` | `-/0` | `0` | `1` |
| No records match the filters | No rows | — | `0` | `1` |

A test passes only when its command exits with `0` and reports at least one executed test with no failures, errors, skips or pending steps. A failed test still fails the command when other tests are deferred. Invalid configuration, definition files, options and unrecoverable execution errors still exit with `2`, regardless of `--strict`.

### Manual tests

Set `run: manual` on a [test reference](definitions.md#tests) to defer expensive tests until requested. The default is `run: auto`. Use:

```console
requirements spec
requirements spec --all
requirements spec --all --label=fuzz
requirements spec --all --strict
```

`--all` includes manual tests but still respects filters and the unsupported status. `--no-test` takes precedence over `--all`. `--strict` checks test linkage, not whether every linked test was run: use `--all --strict` to run all selected supported specifications and require test links.

Each distinct runner/target pair runs at most once. When selected supported specifications share a target, an automatic reference schedules it and its result is reused for every reference, including manual ones, regardless of definition order. Mark every reference to an expensive target as manual to keep it out of a default run.

The JSON report includes `deferred_targets` per record, alongside `passed_targets`, `total_targets`, and the executed case count `tests`. Deferred targets never count as passed. Top-level `strict`, `all` and `no_test` record the requested options; `passed` indicates whether the command's selected gates succeeded, not that every specification was verified. No matching records produces an empty report by default.

To retain the previous failure on unlinked specifications or empty selections in CI, change `requirements spec` to `requirements spec --strict`.

## CI

To require full coverage of what a pull request adds or changes, commit a coverage snapshot and compare against the one on the base branch:

```console
requirements coverage --write-snapshot requirements-snapshot.json
```

```sh
git show "origin/$GITHUB_BASE_REF:requirements-snapshot.json" > /tmp/base-snapshot.json
requirements coverage --snapshot /tmp/base-snapshot.json --min-diff-coverage 100
```

The snapshot lists every unit in scope with a fingerprint of its text and of the items that quote it; it contains no source text. A unit is new or changed when its fingerprint differs, including when a quoting item's statement, status, reason or tests change. Take the snapshot from the base branch, never from the pull request itself. A unit that disappears from the scope fails the run unless `--allow-removed` is given.
