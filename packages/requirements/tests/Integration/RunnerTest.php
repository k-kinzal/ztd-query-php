<?php

declare(strict_types=1);

namespace Requirements\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Test\BehatRunner;
use Requirements\Test\PhpUnitRunner;
use Requirements\Test\RunnerConfig;

final class RunnerTest extends TestCase
{
    #[DataProvider('phpunitTargets')]
    public function testPhpUnitRunsOnlyExactMethodAndRequiresRealPassingTests(string $method, string $status, int $tests): void
    {
        $package = dirname(__DIR__, 2);
        $config = new RunnerConfig('phpunit', [PHP_BINARY, $package . '/vendor/bin/phpunit', '--no-configuration', $package . '/tests/Fixtures/PassingTest.php'], $package);
        $result = (new PhpUnitRunner())->run($config, 'Requirements\\Tests\\Fixtures\\PassingTest::' . $method);
        self::assertSame($status, $result->status, $result->message);
        self::assertSame($tests, $result->tests, $result->message);
    }

    /** @return list<array{string, string, int}> */
    public static function phpunitTargets(): array
    {
        return [['testPass', 'passed', 1], ['testFailure', 'failed', 1], ['testSkip', 'failed', 1], ['testMissing', 'error', 0], ['testData', 'passed', 2]];
    }

    #[DataProvider('behatTargets')]
    public function testBehatRunsScenarioAndOutlineAndRejectsUndefinedOrMissingTests(int $line, string $status, int $tests): void
    {
        $package = dirname(__DIR__, 2);
        $config = new RunnerConfig('behat', [PHP_BINARY, $package . '/vendor/bin/behat'], $package . '/tests/Fixtures');
        $result = (new BehatRunner())->run($config, 'example.feature:' . $line);
        self::assertSame($status, $result->status, $result->message);
        self::assertSame($tests, $result->tests, $result->message);
    }

    /** @return list<array{int, string, int}> */
    public static function behatTargets(): array
    {
        return [[2, 'passed', 1], [4, 'failed', 1], [6, 'failed', 1], [8, 'passed', 2], [3, 'error', 0], [99, 'error', 0]];
    }
}
