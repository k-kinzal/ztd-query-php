# Configuration

The configuration file tells requirements which definition files to load, how to run the linked tests, and which coverage the project must reach. Commands read `requirements.yaml` in the working directory; pass `--config FILE` to use another.

## Example

```yaml
$schema: https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/config.schema.json
version: 1
definitions:
  - requirements/*.yaml
runners:
  unit:
    extension: phpunit
    command: [php, vendor/bin/phpunit]
  behavior:
    extension: behat
    command: [php, vendor/bin/behat]
coverage:
  minimum: 80
```

The runner names, here `unit` and `behavior`, are what definition files write in `tests[].runner`.

## Fields

| Key | Required | Description |
|-----|----------|-------------|
| `version` | yes | Always `1`. |
| `definitions` | yes | Definition files to load, as PHP `glob` patterns. `*` does not cross directories. Every pattern must match at least one file; a file matched twice is loaded once. |
| `$schema` | no | The configuration schema, for lint and editors. See [Schema](#schema). |
| `bootstrap` | no | A PHP file loaded before anything else, usually `vendor/autoload.php`, so that custom extensions can be found. |
| `extensions.sources` | no | Custom source formats, as `name: Class`. See [extensions](extensions.md). |
| `extensions.runners` | no | Custom test runners, as `name: Class`. See [extensions](extensions.md). |
| `runners.<name>.extension` | yes | The runner to use: `phpunit`, `behat`, or a registered custom runner. |
| `runners.<name>.command` | yes | The command as an argument list, for example `[php, vendor/bin/phpunit]`. It is never run through a shell. |
| `runners.<name>.cwd` | no | Working directory of the command. Default `.`. |
| `runners.<name>.timeout` | no | Timeout in seconds. Default `60`. |
| `coverage.minimum` | no | Minimum source coverage in percent that `coverage` requires. Default `0`. |
| `coverage.diff_minimum` | no | Minimum coverage in percent of the units that are new or changed since a snapshot. Default `0`. See [CI](cli.md#ci). |
| `coverage.sources.<id>` | no | Minimum coverage in percent for one source, by source ID. |
| `markdown.experimental` | no | Set to `true` to allow Markdown definition files. See [definitions](definitions.md#markdown-definitions). |

Relative paths are resolved from the directory of the configuration file, including paths written inside definition files. Unknown keys fail lint.

The bootstrap file and extension classes are project code: requirements runs them the same way it runs your test suite.

## Schema

The `$schema` URI above resolves to the schema installed with the package, so lint works offline. A relative path such as `./team.schema.json` adds a local JSON Schema on top of the bundled one; it cannot weaken it.

Editors that use YAML Language Server read the schema from a `# yaml-language-server: $schema=...` comment or from an editor setting. Prefer the editor setting if you run `requirements format`, because it removes comments.
