<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Test\JUnit;
use Requirements\Test\ProcessRunner;
use Requirements\Test\RunnerConfig;
use Requirements\Test\TestResult;
use Tests\Fake\ProjectDirectory;

#[CoversClass(ProcessRunner::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(JUnit::class)]
#[UsesClass(TestResult::class)]
#[Medium]
final class ProcessRunnerTest extends TestCase
{
    public function testRunFailsOnTimeoutAndMissingReport(): void
    {
        $project = new ProjectDirectory();
        $runner = new ProcessRunner();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'usleep(500000);'], $project->directory, 0.05);
        self::assertSame('error', $runner->run($config, static fn (string $directory): array => [])->status);
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'exit(0);'], $project->directory);
        self::assertSame('error', $runner->run($config, static fn (string $directory): array => [])->status);
    }

    public function testRunReportsTheTimeoutMessage(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'usleep(500000);'], $project->directory, 0.05);
        $result = (new ProcessRunner())->run($config, static fn (string $directory): array => []);
        self::assertSame(0, $result->tests);
        self::assertStringContainsString('exceeded the timeout of 0.05 seconds', $result->message);
    }

    public function testRunReportsTheOutputOfARunWithoutReports(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'echo "to stdout\n"; fwrite(STDERR, "to stderr\n");'], $project->directory);
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => "No executed tests in fresh JUnit reports. to stdout\nto stderr"], (new ProcessRunner())->run($config, static fn (string $directory): array => [])->toArray());
    }

    public function testRunReadsTheReportWrittenToTheFreshDirectory(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'file_put_contents($argv[1], "<testsuite><testcase name=\"a\"/><testcase name=\"b\"/></testsuite>");'], $project->directory);
        $result = (new ProcessRunner())->run($config, static fn (string $directory): array => [$directory . '/report.xml']);
        self::assertSame(['status' => 'passed', 'tests' => 2, 'message' => ''], $result->toArray());
    }

    public function testRunAppendsTheArgumentsToTheCommand(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'file_put_contents($argv[2], "<testsuite><testcase name=\"" . $argv[1] . "\"/></testsuite>"); echo $argv[1];', 'fixed'], $project->directory);
        $result = (new ProcessRunner())->run($config, static fn (string $directory): array => [$directory . '/report.xml']);
        self::assertSame('passed', $result->status);
    }

    public function testRunRunsInTheConfiguredDirectory(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'file_put_contents("cwd.txt", "here"); file_put_contents($argv[1], "<testsuite><testcase/></testsuite>");'], $project->directory);
        (new ProcessRunner())->run($config, static fn (string $directory): array => [$directory . '/report.xml']);
        self::assertSame('here', $project->read('cwd.txt'));
    }

    public function testRunReadsEveryXmlReportAndIgnoresOtherFiles(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'file_put_contents($argv[1] . "/a.xml", "<testsuite><testcase/></testsuite>"); file_put_contents($argv[1] . "/b.xml", "<testsuite><testcase/></testsuite>"); file_put_contents($argv[1] . "/notes.txt", "<testsuite><testcase><failure/></testcase></testsuite>");'], $project->directory);
        $result = (new ProcessRunner())->run($config, static fn (string $directory): array => [$directory]);
        self::assertSame(['status' => 'passed', 'tests' => 2, 'message' => ''], $result->toArray());
    }

    public function testRunFailsWithTheOutputOfAFailingExit(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'file_put_contents($argv[1], "<testsuite><testcase/></testsuite>"); echo "broken\n"; exit(3);'], $project->directory);
        $result = (new ProcessRunner())->run($config, static fn (string $directory): array => [$directory . '/report.xml']);
        self::assertSame(['status' => 'failed', 'tests' => 1, 'message' => 'broken'], $result->toArray());
    }

    public function testRunRemovesTheReportDirectory(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'file_put_contents($argv[2] . "/report.xml", "<testsuite><testcase/></testsuite>"); file_put_contents($argv[2] . "/log.txt", "x"); file_put_contents($argv[1], $argv[2]);', $project->path('seen.txt')], $project->directory);
        (new ProcessRunner())->run($config, static fn (string $directory): array => [$directory]);
        self::assertStringStartsWith(sys_get_temp_dir() . '/requirements-', $project->read('seen.txt'));
        self::assertDirectoryDoesNotExist($project->read('seen.txt'));
    }

    public function testRunReportsACommandThatCannotStart(): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'exit(0);'], $project->path('missing'));
        $result = (new ProcessRunner())->run($config, static fn (string $directory): array => []);
        self::assertSame('error', $result->status);
        self::assertSame(0, $result->tests);
        self::assertStringContainsString('cwd', $result->message);
    }
}
