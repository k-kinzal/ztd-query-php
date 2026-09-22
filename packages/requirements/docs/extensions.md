# Source and test extensions

A **source extension** retrieves reference material and selects its traceable units.
A **test extension** runs a selected test and translates its result into specification
verification. The test extension interface is named `RunnerExtension`; configuration
registers it under `extensions.runners`.

Both extension mechanisms are implemented by the core. Built-in HTML, JSON and other
sources use `SourceExtension`, and the PHPUnit/Behat runners use `RunnerExtension`.
Custom implementations run through the same `check`, `coverage` and `spec` commands.
They can be used without changing the requirements package or installing a plugin
into PHPUnit or Behat.

## Source extensions

A source extension implements this interface:

```php
namespace Requirements\Source;

use Requirements\Model\Source;

interface SourceExtension
{
    /** @return list<Unit> */
    public function select(Source $source, string $selector, string $directory, bool $live): array;
}
```

`Source` supplies `id`, `uri`, `format`, `selector`, optional `snapshot` and `sha256`,
and an `options` mapping for service-specific settings. `$directory` is the directory
containing the project's configuration. `$live` requests current upstream content
rather than a pinned local cache; custom adapters decide how this applies to their
service.

The core calls `select()` first with the source's scope selector to enumerate the
coverage denominator. It then calls it for each evidence selector. Scope selection
may return many units; an evidence selection must return exactly one unit from that
scope. Return `new Unit($stableLocation, $completeText)` for each unit:

- Locations identify the same entry across requests and revisions. Use a stable
  issue criterion ID, message ID or document section location, rather than its text.
- Locations and text must be nonempty. Equal locations in one resource must have
  equal text. Distinct locations can contain equal text.
- Enumerate the scope independently of known specifications; otherwise missing
  specifications disappear from the coverage denominator.
- Interpret supported selectors consistently. Return an empty list for a valid
  selector with no match; throw for retrieval failures or unsupported syntax.
  The core reports these failures and checks quote equality and scope membership.

A Slack or Jira adapter owns service retrieval, pagination and selector semantics.
It can load its client from the consuming project's Composer autoloader, read
credentials from the environment, and use `Source::options` for non-secret settings.
When an API returns multiple pages, scope selection must enumerate every selected
page. The core does not assume those services behave like HTML.

### Create a source adapter

The runnable [CatalogSource](../examples/extensions/CatalogSource.php) reads a JSON
mapping of stable IDs to source text. `*` selects the whole catalog; an entry ID
selects one quotation:

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

`ResourceLoader` is optional. It supplies bounded HTTP(S)/local reads, ignored local
snapshot caching and digest verification. Service adapters can use their own
retrieval client and enforce their own time/size limits. The example catalog is
project-authored synthetic data, so it can be committed; upstream snapshots remain
untracked caches.

## Test extensions

A test extension implements:

```php
namespace Requirements\Test;

interface RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult;
}
```

`RunnerConfig` contains an argument-array `command`, absolute working `directory`,
positive `timeout` in seconds and the registered `extension` name. `$target` is the
test selection from an item's `tests` entry. Configuration chooses **how** to execute;
definitions choose **which** test to execute.

Return `new TestResult($status, $executedTests, $message)`. A passing result requires
`status: passed` and a positive executed-test count. `failed`, `error` and
`unverified` fail specification verification. A zero-test result never verifies a
specification, even if the adapter returns `passed`. Include a useful failure
message. Unsupported specifications are skipped by the core; an adapter must not
turn failed or missing tests into an unsupported decision.

The core executes each distinct runner-name/target pair once per `spec` invocation
and shares that result across linked specifications. All linked results must pass.
A custom extension owns actual selection, execution and result interpretation; it
must not report success solely because a process exited zero.

### Create a test adapter

For a command that writes JUnit XML, use `ProcessRunner`:

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

`ProcessRunner` appends those arguments to `command` without a shell, applies the
working directory and timeout, reads reports from a fresh temporary directory, and
removes them afterwards. Its `JUnit` reader rejects missing/malformed reports,
zero executed tests, skips, pending/undefined cases, failures, errors and nonzero
process exits. For another report protocol, execute and parse it in your adapter
and enforce equivalent success rules.

The complete [ScenarioRunner](../examples/extensions/ScenarioRunner.php) and
[example command](../examples/extensions/scenario.php) exercise this contract by
actually checking ASCII uppercase conversion. Replace the example assertion with
your application tests. The built-in PHPUnit runner uses exact `Class::method`
selection, including datasets; Behat uses `file.feature:line` scenario selection,
including all outline examples.

## Register and use extensions

Put extension classes in your project's Composer PSR-4 autoload paths, run
`composer dump-autoload`, and load that autoloader with `bootstrap`:

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
    cwd: .
    timeout: 30
```

Classes are instantiated with no arguments; use a no-argument constructor or
constructor defaults. `lint` checks interface registration. The bootstrap and
extensions are project code, just like the project's test suite. Names may override
built-ins deliberately; normal built-in usage needs no explicit registration.

Select the source adapter with `source.format` and the configured test runner with
`tests[].runner`:

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

Here `catalog` and `scenario` are extension names; `acceptance` is the configured
runner instance. Several runner instances may share one extension with different
commands, working directories or timeouts. Definition paths, source files and
runner working directories are resolved relative to the configuration directory.

From `packages/requirements`, run the complete bundled example:

```console
php bin/requirements lint --config examples/extensions/requirements.yaml
php bin/requirements check --config examples/extensions/requirements.yaml
php bin/requirements coverage --config examples/extensions/requirements.yaml
php bin/requirements spec --config examples/extensions/requirements.yaml
```

The sample bootstrap loads the two supplied classes directly, so these commands
work after `composer install` without preparing another project. The integration
tests execute this example through the real CLI and verify that both extension
contracts participate in source coverage and test execution.
