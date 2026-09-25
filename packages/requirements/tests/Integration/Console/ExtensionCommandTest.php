<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Bootstrap;
use Requirements\Console\Application;
use Requirements\Console\CommandHandler;
use Requirements\Console\CommandLine;
use Requirements\Console\Executor;
use Requirements\Source\ResourceLoader;
use Requirements\Test\ProcessRunner;
use Tests\Fake\CommandLine as Cli;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Application::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(CommandHandler::class)]
#[UsesClass(Executor::class)]
#[UsesClass(Bootstrap::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ProcessRunner::class)]
#[Medium]
final class ExtensionCommandTest extends TestCase
{
    #[DataProvider('providerCommands')]
    public function testRunExecutesCustomSourcesAndRunnersRegisteredThroughABootstrapFile(string $command, string $expected): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', [
            'version' => 1,
            'bootstrap' => 'bootstrap.php',
            'definitions' => ['catalog.yaml'],
            'extensions' => ['sources' => ['catalog' => 'SampleExtension\\CatalogSource'], 'runners' => ['scenario' => 'SampleExtension\\ScenarioRunner']],
            'runners' => ['acceptance' => ['extension' => 'scenario', 'command' => [PHP_BINARY, 'scenario.php'], 'timeout' => 10]],
            'coverage' => ['minimum' => 100],
        ]);
        $project->write('catalog.yaml', [
            'version' => 1,
            'source' => ['id' => 'converter-catalog', 'uri' => 'catalog.json', 'format' => 'catalog', 'selector' => '*'],
            'items' => [[
                'id' => 'CONVERTER-001',
                'statement' => 'When text contains lowercase ASCII letters, the converter shall uppercase those letters.',
                'evidence' => [['selector' => 'ascii-uppercase', 'quote' => 'The converter uppercases lowercase ASCII letters.']],
                'tests' => [['runner' => 'acceptance', 'target' => 'ascii-uppercase']],
            ]],
        ]);
        $project->put('catalog.json', "{\n  \"ascii-uppercase\": \"The converter uppercases lowercase ASCII letters.\"\n}\n");
        $project->put('bootstrap.php', <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/CatalogSource.php';
require_once __DIR__ . '/ScenarioRunner.php';
PHP);
        $project->put('CatalogSource.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace SampleExtension;

use Requirements\Model\Source;
use Requirements\Source\ResourceLoader;
use Requirements\Source\SourceExtension;
use Requirements\Source\Unit;
use RuntimeException;
use stdClass;

final class CatalogSource implements SourceExtension
{
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    /** @return list<Unit> */
    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        $catalog = json_decode($this->loader->read($source, $directory, $live), false, 512, JSON_THROW_ON_ERROR);
        if (!$catalog instanceof stdClass) {
            throw new RuntimeException('The catalog must map stable entry IDs to source text.');
        }
        $units = [];
        foreach (get_object_vars($catalog) as $id => $text) {
            if (!is_string($id) || $id === '' || !is_string($text) || trim($text) === '') {
                throw new RuntimeException('Every catalog entry needs a nonempty ID and text.');
            }
            if ($selector === '*' || $selector === $id) {
                $units[] = new Unit('entry:' . $id, $text);
            }
        }
        return $units;
    }
}
PHP);
        $project->put('ScenarioRunner.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace SampleExtension;

use Requirements\Test\ProcessRunner;
use Requirements\Test\RunnerConfig;
use Requirements\Test\RunnerExtension;
use Requirements\Test\TestResult;

final class ScenarioRunner implements RunnerExtension
{
    public function run(RunnerConfig $config, string $target): TestResult
    {
        return (new ProcessRunner())->run($config, static fn (string $directory): array => ['--case', $target, '--junit', $directory . '/results.xml']);
    }
}
PHP);
        $project->put('scenario.php', <<<'PHP'
<?php

declare(strict_types=1);

$options = getopt('', ['case:', 'junit:']);
if ($options === false || !isset($options['case'], $options['junit']) || !is_string($options['case']) || !is_string($options['junit'])) {
    fwrite(STDERR, "Usage: php scenario.php --case ascii-uppercase --junit FILE\n");
    exit(2);
}
if ($options['case'] !== 'ascii-uppercase') {
    fwrite(STDERR, "Unknown case.\n");
    exit(2);
}
$passed = strtoupper('example') === 'EXAMPLE';
$failure = $passed ? '' : '<failure message="Expected uppercase ASCII letters"/>';
$xml = '<testsuite tests="1"><testcase name="ascii-uppercase">' . $failure . '</testcase></testsuite>';
if (file_put_contents($options['junit'], $xml) === false) {
    exit(2);
}
exit($passed ? 0 : 1);
PHP);
        $process = Cli::run([$command, '--config', $project->path('requirements.yaml'), '--json'], $project->directory);
        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('"passed": true', $process->getOutput());
        self::assertStringContainsString($expected, $process->getOutput());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerCommands(): array
    {
        return [
            'lint' => ['lint', '"message": "1 items validated."'],
            'check' => ['check', '"CONVERTER-001"'],
            'coverage' => ['coverage', '"percentage": 100'],
            'spec' => ['spec', '"tests": 1'],
        ];
    }
}
