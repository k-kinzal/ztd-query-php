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
| `spec` | `--no-test` | List the records without running tests. |
| `spec` | `--strict` | Fail when no records match or a selected supported specification has no linked tests. |
| `spec` | `--all` | Include tests marked `run: manual`. |
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

`spec` runs each automatic test once, even when several specifications share it, and shows **passed / linked** tests per record. A data provider or scenario outline counts as one test.

| Situation | Result | Tests | Fails `spec` |
|-----------|--------|-------|--------------|
| All three linked tests pass | `passed` | `3/3` | no |
| One of three fails, is skipped or runs no test | `failed` | `2/3` | yes |
| Supported specification without tests | `unverified` | `0/0` | only with `--strict` |
| Two tests pass; one manual test is deferred | `deferred` | `2/3` | no |
| Unsupported specification | `unsupported` | `-/3` | no |
| Requirement | `not-applicable` | `-/0` | no |
| Run with `--no-test` | `not-run` | `-/3` | no |

A test passes only when its command exits with `0` and reports at least one executed test with no failures, errors, skips or pending steps.

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
