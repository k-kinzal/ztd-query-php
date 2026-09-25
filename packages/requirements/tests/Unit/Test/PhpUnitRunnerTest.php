<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Test\JUnit;
use Requirements\Test\PhpUnitRunner;
use Requirements\Test\ProcessRunner;
use Requirements\Test\RunnerConfig;
use Requirements\Test\TestResult;
use Tests\Fake\PhpUnitSuite;
use Tests\Fake\ProjectDirectory;

#[CoversClass(PhpUnitRunner::class)]
#[UsesClass(ProcessRunner::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(JUnit::class)]
#[UsesClass(TestResult::class)]
#[Medium]
final class PhpUnitRunnerTest extends TestCase
{
    #[DataProvider('providerRunRunsOnlyExactMethodAndRequiresRealPassingTests')]
    public function testRunRunsOnlyExactMethodAndRequiresRealPassingTests(string $method, string $status, int $tests): void
    {
        $project = new ProjectDirectory();
        $command = PhpUnitSuite::write($project->directory);
        self::assertNotEmpty($command);
        $config = new RunnerConfig('phpunit', $command, $project->directory);
        $result = (new PhpUnitRunner())->run($config, 'Sample\\PassingTest::' . $method);
        self::assertSame($status, $result->status, $result->message);
        self::assertSame($tests, $result->tests, $result->message);
    }

    /**
     * @return list<array{string, string, int}>
     */
    public static function providerRunRunsOnlyExactMethodAndRequiresRealPassingTests(): array
    {
        return [['testPass', 'passed', 1], ['testFailure', 'failed', 1], ['testSkip', 'failed', 1], ['testMissing', 'error', 0], ['testData', 'passed', 2]];
    }

    public function testRunReportsTheFailureOutput(): void
    {
        $project = new ProjectDirectory();
        $command = PhpUnitSuite::write($project->directory);
        self::assertNotEmpty($command);
        $config = new RunnerConfig('phpunit', $command, $project->directory);
        $result = (new PhpUnitRunner())->run($config, 'Sample\\PassingTest::testFailure');
        self::assertStringContainsString('Expected failure.', $result->message);
    }

    public function testRunTreatsTheTargetAsLiteralText(): void
    {
        $project = new ProjectDirectory();
        $command = PhpUnitSuite::write($project->directory);
        self::assertNotEmpty($command);
        $config = new RunnerConfig('phpunit', $command, $project->directory);
        $result = (new PhpUnitRunner())->run($config, 'Sample\\PassingTest::testPas');
        self::assertSame('error', $result->status, $result->message);
        self::assertSame(0, $result->tests, $result->message);
    }

    #[DataProvider('providerRunRejectsMalformedTargets')]
    public function testRunRejectsMalformedTargets(string $target): void
    {
        $project = new ProjectDirectory();
        $config = new RunnerConfig('phpunit', [PHP_BINARY, '-r', 'file_put_contents("ran.txt", "ran");'], $project->directory);
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'PHPUnit targets must be fully qualified Class::method references.'], (new PhpUnitRunner())->run($config, $target)->toArray());
        self::assertFileDoesNotExist($project->path('ran.txt'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerRunRejectsMalformedTargets(): array
    {
        return [
            'class only' => ['Sample\\PassingTest'],
            'method only' => ['testPass'],
            'empty method' => ['Sample\\PassingTest::'],
            'leading digit in class' => ['1Sample\\PassingTest::testPass'],
            'leading digit in method' => ['Sample\\PassingTest::1test'],
            'leading backslash' => ['\\Sample\\PassingTest::testPass'],
            'option' => ['--filter=x::y'],
            'regex in method' => ['Sample\\PassingTest::test.*'],
            'space' => ['Sample\\PassingTest::test Pass'],
            'trailing newline' => ["Sample\\PassingTest::testPass\n"],
            'data set' => ['Sample\\PassingTest::testData with data set #0'],
        ];
    }
}
