<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\Application;
use Requirements\Console\CommandHandler;
use Requirements\Console\CommandLine;
use Requirements\Console\Executor;
use Requirements\Console\Overview;
use Requirements\Console\Reporter;
use Requirements\Console\SpecificationReport;
use Tests\Fake\CommandLine as Cli;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Application::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(Overview::class)]
#[UsesClass(CommandHandler::class)]
#[UsesClass(Executor::class)]
#[UsesClass(SpecificationReport::class)]
#[UsesClass(Reporter::class)]
#[Large]
final class CommandsTest extends TestCase
{
    #[DataProvider('providerCommands')]
    public function testRunExitsZeroForEachCommandAsJson(string $command): void
    {
        $project = new ProjectDirectory();
        $process = Cli::run([$command, '--json'], $project->directory);
        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerCommands(): array
    {
        return [
            'lint' => ['lint'],
            'check' => ['check'],
            'coverage' => ['coverage'],
            'format' => ['format'],
        ];
    }

    public function testRunChecksCoverageFormatsAndFiltersIndependentSpecifications(): void
    {
        $project = new ProjectDirectory();
        $format = Cli::run(['format', '--check'], $project->directory);
        self::assertSame(0, $format->getExitCode(), $format->getOutput());
        $gate = Cli::run(['coverage', '--min-coverage=100'], $project->directory);
        self::assertSame(1, $gate->getExitCode());
        $spec = Cli::run(['spec'], $project->directory);
        self::assertSame(0, $spec->getExitCode());
        self::assertStringContainsString('unverified', $spec->getOutput());
        $project->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'ORIGINAL-001', 'statement' => 'The parser shall reject truncated input.', 'origin' => 'original', 'reason' => 'Avoid silent data loss.', 'labels' => ['strictness']]]]);
        $list = Cli::run(['spec', '--no-test', '--without-source', '--label=strictness', '--json'], $project->directory);
        self::assertSame(0, $list->getExitCode());
        self::assertStringContainsString('ORIGINAL-001', $list->getOutput());
        $empty = Cli::run(['spec', '--id=MISSING'], $project->directory);
        self::assertSame(0, $empty->getExitCode());
    }

    public function testRunExitsTwoForAnInvalidPercentageAndAnUnknownOption(): void
    {
        $project = new ProjectDirectory();
        $process = Cli::run(['coverage', '--min-coverage=not-a-number', '--json'], $project->directory);
        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('percentage', $process->getOutput());
        $process = Cli::run(['spec', '--unknown'], $project->directory);
        self::assertSame(2, $process->getExitCode());
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('providerHelp')]
    public function testRunShowsHelpWithoutAProject(array $arguments): void
    {
        $project = new ProjectDirectory();
        unlink($project->path('requirements.yaml'));
        $process = Cli::run($arguments, $project->directory);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('Usage:', $process->getOutput());
        self::assertStringNotContainsString("\x1b", $process->getOutput());
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function providerHelp(): array
    {
        return [
            'nothing' => [[]],
            'help option' => [['--help']],
            'help shortcut' => [['-h']],
            'help without decoration' => [['--help', '--no-ansi']],
            'help with a missing configuration' => [['--config', 'missing.yaml', '--help']],
            'help with json' => [['--json', '--help']],
            'help command for a command' => [['help', 'coverage']],
            'command help' => [['coverage', '--help']],
            'command help shortcut' => [['spec', '-h']],
            'list' => [['list']],
        ];
    }

    public function testRunShowsTheOverviewAndTheOptionsOfEachCommandWithoutAProject(): void
    {
        $project = new ProjectDirectory();
        unlink($project->path('requirements.yaml'));
        $overview = Cli::run(['--config', 'missing.yaml', '--help'], $project->directory);
        self::assertSame(0, $overview->getExitCode());
        self::assertStringContainsString('Available commands:', $overview->getOutput());
        $help = Cli::run(['coverage', '--help'], $project->directory);
        self::assertSame(0, $help->getExitCode());
        self::assertStringContainsString('--min-diff-coverage', $help->getOutput());
        self::assertStringContainsString('--snapshot', $help->getOutput());
        self::assertStringContainsString('--write-snapshot', $help->getOutput());
        self::assertStringNotContainsString('baseline', $help->getOutput());
        self::assertStringNotContainsString('--without-source', $help->getOutput());
        $help = Cli::run(['spec', '--help'], $project->directory);
        self::assertSame(0, $help->getExitCode());
        self::assertStringContainsString('--no-test', $help->getOutput());
        self::assertStringContainsString('--strict', $help->getOutput());
        self::assertStringContainsString('--all', $help->getOutput());
        self::assertStringContainsString('--without-source', $help->getOutput());
    }

    public function testRunAcceptsGlobalOptionsBeforeTheCommandAndRendersTables(): void
    {
        $project = new ProjectDirectory();
        $process = Cli::run(['--config', 'requirements.yaml', 'coverage', '--no-ansi'], $project->directory);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('33.33%', $process->getOutput());
        self::assertStringContainsString('Uncovered', $process->getOutput());
        self::assertStringNotContainsString('passed:', $process->getOutput());
        self::assertStringNotContainsString('{  }', $process->getOutput());
        $list = Cli::run(['spec', '--no-test', '--no-ansi'], $project->directory);
        self::assertSame(0, $list->getExitCode());
        self::assertStringContainsString('SPEC-001', $list->getOutput());
        self::assertStringContainsString('Statement', $list->getOutput());
    }

    /**
     * @throws JsonException
     */
    public function testRunWritesAndComparesCoverageSnapshots(): void
    {
        $project = new ProjectDirectory();
        $write = Cli::run(['coverage', '--write-snapshot=snapshot.json', '--json'], $project->directory);
        self::assertSame(0, $write->getExitCode(), $write->getOutput() . $write->getErrorOutput());
        $snapshot = json_decode($project->read('snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);
        self::assertSame('requirements-snapshot', $snapshot['type']);
        self::assertSame(['version', 'type', 'units'], array_keys($snapshot));
        $compare = Cli::run(['coverage', '--snapshot=snapshot.json', '--min-diff-coverage=100', '--json'], $project->directory);
        self::assertSame(0, $compare->getExitCode(), $compare->getOutput() . $compare->getErrorOutput());
        $report = json_decode($compare->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame(['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null], $report['diff']);
        self::assertSame([], $report['removed']);
        $missing = Cli::run(['coverage', '--min-diff-coverage=100', '--no-ansi'], $project->directory);
        self::assertSame(1, $missing->getExitCode());
        self::assertStringContainsString('requires --snapshot', $missing->getOutput());
    }

    /**
     * @param list<string> $arguments
     *
     * @throws JsonException
     */
    #[DataProvider('providerInvalidInput')]
    public function testRunKeepsJsonMachineReadableForInvalidInput(array $arguments): void
    {
        $project = new ProjectDirectory();
        $process = Cli::run(['--json', ...$arguments], $project->directory);
        self::assertSame(2, $process->getExitCode());
        $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertFalse($report['passed']);
        self::assertSame('', $process->getErrorOutput());
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function providerInvalidInput(): array
    {
        return [
            'unknown option' => [['coverage', '--unknown']],
            'removed baseline option' => [['coverage', '--baseline=snapshot.json']],
            'removed write-baseline option' => [['coverage', '--write-baseline=snapshot.json']],
            'option of another command' => [['spec', '--live']],
            'unknown command' => [['unknown']],
            'missing configuration' => [['--config', 'missing.yaml', 'lint']],
        ];
    }

    public function testRunReportsAMissingConfigurationOnStandardError(): void
    {
        $project = new ProjectDirectory();
        $process = Cli::run(['lint', '--config', 'missing.yaml', '--no-ansi'], $project->directory);
        self::assertSame(2, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('[ERROR]', $process->getErrorOutput());
        self::assertStringContainsString('Configuration does not exist', $process->getErrorOutput());
    }
}
