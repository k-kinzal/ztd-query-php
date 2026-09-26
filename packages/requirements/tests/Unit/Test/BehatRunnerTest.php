<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Test\BehatRunner;
use Requirements\Test\JUnit;
use Requirements\Test\ProcessRunner;
use Requirements\Test\RunnerConfig;
use Requirements\Test\TestResult;
use Tests\Fake\BehatSuite;
use Tests\Fake\ProjectDirectory;

#[CoversClass(BehatRunner::class)]
#[UsesClass(ProcessRunner::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(JUnit::class)]
#[UsesClass(TestResult::class)]
#[Medium]
final class BehatRunnerTest extends TestCase
{
    #[DataProvider('providerRunRunsScenarioAndOutlineAndRejectsUndefinedOrMissingTests')]
    public function testRunRunsScenarioAndOutlineAndRejectsUndefinedOrMissingTests(int $line, string $status, int $tests): void
    {
        $project = new ProjectDirectory();
        $command = BehatSuite::write($project->directory);
        self::assertNotEmpty($command);
        $config = new RunnerConfig('behat', $command, $project->directory);
        $result = (new BehatRunner())->run($config, 'example.feature:' . $line);
        self::assertSame($status, $result->status, $result->message);
        self::assertSame($tests, $result->tests, $result->message);
    }

    /**
     * @return list<array{int, string, int}>
     */
    public static function providerRunRunsScenarioAndOutlineAndRejectsUndefinedOrMissingTests(): array
    {
        return [[2, 'passed', 1], [4, 'failed', 1], [6, 'failed', 1], [8, 'passed', 2], [3, 'error', 0], [99, 'error', 0]];
    }

    public function testRunAcceptsAnAbsoluteFeaturePath(): void
    {
        $project = new ProjectDirectory();
        $command = BehatSuite::write($project->directory);
        self::assertNotEmpty($command);
        $config = new RunnerConfig('behat', $command, $project->directory);
        $result = (new BehatRunner())->run($config, $project->path('example.feature') . ':2');
        self::assertSame('passed', $result->status, $result->message);
        self::assertSame(1, $result->tests, $result->message);
    }

    #[DataProvider('providerRunRejectsMalformedTargets')]
    public function testRunRejectsMalformedTargets(string $target): void
    {
        $project = new ProjectDirectory();
        $project->put('-example.feature', "Feature: x\n  Scenario: y\n");
        $config = new RunnerConfig('behat', [PHP_BINARY, '-r', 'file_put_contents("ran.txt", "ran");'], $project->directory);
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'Behat targets must select one scenario by file.feature:line.'], (new BehatRunner())->run($config, $target)->toArray());
        self::assertFileDoesNotExist($project->path('ran.txt'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerRunRejectsMalformedTargets(): array
    {
        return [
            'no line' => ['example.feature'],
            'empty line' => ['example.feature:'],
            'line zero' => ['example.feature:0'],
            'leading zero' => ['example.feature:02'],
            'not a feature' => ['example.txt:2'],
            'no file name' => [':2'],
            'option' => ['-example.feature:2'],
            'trailing newline' => ["example.feature:2\n"],
            'line range' => ['example.feature:2-4'],
        ];
    }

    /**
     * @param array<string, string> $files
     */
    #[DataProvider('providerRunRejectsLinesWithoutAScenarioHeader')]
    public function testRunRejectsLinesWithoutAScenarioHeader(array $files, string $target): void
    {
        $project = new ProjectDirectory();
        array_map($project->put(...), array_keys($files), $files);
        $config = new RunnerConfig('behat', [PHP_BINARY, '-r', 'file_put_contents("ran.txt", "ran");'], $project->directory);
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'The target line must be an English Scenario or Scenario Outline header.'], (new BehatRunner())->run($config, $target)->toArray());
        self::assertFileDoesNotExist($project->path('ran.txt'));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function providerRunRejectsLinesWithoutAScenarioHeader(): array
    {
        $feature = "Feature: Headers\n  Scenario:\n  Scenario:   \n  Background: setup\n  Scenarios: x\n  # Scenario: commented\n  Szenario: German\n";
        return [
            'missing file' => [[], 'missing.feature:1'],
            'directory' => [['example.feature/inner.txt' => 'x'], 'example.feature:1'],
            'feature line' => [['example.feature' => $feature], 'example.feature:1'],
            'untitled scenario' => [['example.feature' => $feature], 'example.feature:2'],
            'blank title' => [['example.feature' => $feature], 'example.feature:3'],
            'background' => [['example.feature' => $feature], 'example.feature:4'],
            'plural keyword' => [['example.feature' => $feature], 'example.feature:5'],
            'comment' => [['example.feature' => $feature], 'example.feature:6'],
            'other language' => [['example.feature' => $feature], 'example.feature:7'],
            'after the last line' => [['example.feature' => $feature], 'example.feature:8'],
        ];
    }

    public function testRunReadsTheHeaderAtTheExactLine(): void
    {
        $project = new ProjectDirectory();
        $project->put('example.feature', "Feature: Offsets\nScenario: first\n  Given a step\n");
        $project->put('report.xml', '<testsuite><testcase/></testsuite>');
        $config = new RunnerConfig('behat', [PHP_BINARY, '-r', 'copy("report.xml", $argv[array_search("--out", $argv, true) + 1] . "/report.xml");', '--'], $project->directory);
        self::assertSame(['status' => 'passed', 'tests' => 1, 'message' => ''], (new BehatRunner())->run($config, 'example.feature:2')->toArray());
    }

    public function testRunPassesTheTargetAfterTheOptions(): void
    {
        $project = new ProjectDirectory();
        $project->put('example.feature', "Feature: Arguments\n  Scenario Outline: examples\n");
        $config = new RunnerConfig('behat', [PHP_BINARY, '-r', 'file_put_contents("args.txt", implode(" ", array_slice($argv, 1)));', '--'], $project->directory);
        (new BehatRunner())->run($config, 'example.feature:2');
        self::assertMatchesRegularExpression('~^--strict --no-interaction --no-snippets --format junit --out /\S+ -- example\.feature:2$~D', $project->read('args.txt'));
    }
}
