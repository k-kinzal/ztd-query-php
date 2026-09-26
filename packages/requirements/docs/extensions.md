# Extensions

Extensions add source formats and test runners. A **source extension** reads a kind of document, for example an issue tracker, and returns its units. A **runner extension** runs a kind of test and returns its result. The built-in formats and the PHPUnit and Behat runners are implemented the same way.

## Source extensions

```php
namespace Requirements\Source;

use Requirements\Model\Source;

interface SourceExtension
{
    /** @return list<Unit> */
    public function select(Source $source, string $selector, string $directory, bool $live): array;
}
```

| Argument | Description |
|----------|-------------|
| `$source` | The definition's source: `id`, `uri`, `format`, `selector`, `snapshot`, `sha256` and `options`. |
| `$selector` | The scope selector first, then each evidence selector. |
| `$directory` | Directory of the configuration file. |
| `$live` | `true` when `--live` asks for current content instead of a cache. |

Return `new Unit($location, $text)` for every unit the selector matches, or an empty list when it matches nothing. Throw when the document cannot be read or the selector is invalid.

- **Enumerate the whole scope**, independently of what the definitions quote. Otherwise untraced units disappear from coverage. Follow every page of a paged API.
- **Use stable locations**, such as an issue or section ID, not the text. The same location must always have the same text.
- **Keep secrets out of definitions.** Read credentials from the environment and use `options` for other settings.

This extension reads a JSON object of IDs to texts. `*` selects every entry:

```php
use Requirements\Model\Source;
use Requirements\Source\ResourceLoader;
use Requirements\Source\SourceExtension;
use Requirements\Source\Unit;

final class CatalogSource implements SourceExtension
{
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        $catalog = json_decode($this->loader->read($source, $directory, $live), false, 512, JSON_THROW_ON_ERROR);
        if (!$catalog instanceof \stdClass) {
            throw new \RuntimeException('Expected a catalog mapping.');
        }
        $units = [];
        foreach (get_object_vars($catalog) as $id => $text) {
            if (!is_string($id) || $id === '' || !is_string($text) || trim($text) === '') {
                throw new \RuntimeException('Invalid catalog entry.');
            }
            if ($selector === '*' || $selector === $id) {
                $units[] = new Unit('entry:' . $id, $text);
            }
        }
        return $units;
    }
}
```

`ResourceLoader` reads local files and HTTP(S) URIs with the same size and time limits, snapshots and SHA-256 checks as the built-in formats. It is optional.

## Runner extensions

```php
namespace Requirements\Test;

interface RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult;
}
```

`$config` holds the runner's `command`, working `directory`, `timeout` and `extension` from the [configuration](configuration.md). `$target` is the `target` of a test in a definition file. `run()` is called once per distinct target and is not called with `--no-test`.

Return `new TestResult($status, $executedTests, $message)`. Only `passed` with at least one executed test verifies a specification; `failed`, `error` and `unverified` fail it. Do not report success only because a process exited with `0`.

For a command that writes JUnit XML, `ProcessRunner` does the work:

```php
use Requirements\Test\ProcessRunner;
use Requirements\Test\RunnerConfig;
use Requirements\Test\RunnerExtension;
use Requirements\Test\TestResult;

final class ScenarioRunner implements RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult
    {
        return (new ProcessRunner())->run(
            $config,
            static fn (string $directory): array => [
                '--case', $target, '--junit', $directory . '/results.xml',
            ],
        );
    }
}
```

It appends the returned arguments to `command`, runs it without a shell in the working directory with the timeout, and reads the reports from a fresh temporary directory. The run fails when the report is missing or malformed, reports no test, or reports a failure, error, skip or pending test, or when the process exits nonzero.

## Registering extensions

Autoload the classes from your project and load the autoloader with `bootstrap`:

```yaml
version: 1
bootstrap: vendor/autoload.php
definitions: [requirements/*.yaml]
extensions:
  sources:
    catalog: App\Traceability\CatalogSource
  runners:
    scenario: App\Traceability\ScenarioRunner
runners:
  acceptance:
    extension: scenario
    command: [php, tests/scenario.php]
```

A definition then uses the source extension as its `format` and the runner by its configured name:

```yaml
version: 1
source:
  id: converter-catalog
  uri: catalog.json
  format: catalog
  selector: '*'
items:
  - id: CONVERTER-001
    statement: When text contains lowercase ASCII letters, the converter shall uppercase those letters.
    evidence:
      - selector: ascii-uppercase
        quote: The converter uppercases lowercase ASCII letters.
    tests:
      - runner: acceptance
        target: ascii-uppercase
```

Classes are created without constructor arguments, and lint checks that each implements its interface. Several runners can use the same extension with different commands. A registered name that equals a built-in one replaces it.
