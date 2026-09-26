<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Test\RunnerConfig;

#[CoversClass(RunnerConfig::class)]
#[UsesClass(Fields::class)]
#[Small]
final class RunnerConfigTest extends TestCase
{
    public function testFromReadsEveryField(): void
    {
        $config = RunnerConfig::from(['extension' => 'behat', 'command' => ['php', 'vendor/bin/behat'], 'cwd' => 'tests', 'timeout' => 5], '/project');
        self::assertSame('behat', $config->extension);
        self::assertSame(['php', 'vendor/bin/behat'], $config->command);
        self::assertSame('/project/tests', $config->directory);
        self::assertSame(5.0, $config->timeout);
    }

    public function testFromDefaultsToTheConfigurationDirectoryAndSixtySeconds(): void
    {
        $config = RunnerConfig::from(['extension' => 'phpunit', 'command' => ['php', 'vendor/bin/phpunit']], '/project');
        self::assertSame('/project/.', $config->directory);
        self::assertSame(60.0, $config->timeout);
    }

    public function testFromTreatsANullTimeoutAsAbsent(): void
    {
        self::assertSame(60.0, RunnerConfig::from(['extension' => 'phpunit', 'command' => ['phpunit'], 'timeout' => null], '/project')->timeout);
    }

    public function testFromKeepsAnAbsoluteWorkingDirectory(): void
    {
        self::assertSame('/srv/app', RunnerConfig::from(['extension' => 'phpunit', 'command' => ['phpunit'], 'cwd' => '/srv/app'], '/project')->directory);
    }

    public function testFromAcceptsAFractionalTimeout(): void
    {
        self::assertSame(0.5, RunnerConfig::from(['extension' => 'phpunit', 'command' => ['phpunit'], 'timeout' => 0.5], '/project')->timeout);
    }

    public function testFromKeepsRepeatedCommandArguments(): void
    {
        self::assertSame(['php', '-d', 'a=1', '-d', 'b=1'], RunnerConfig::from(['extension' => 'phpunit', 'command' => ['php', '-d', 'a=1', '-d', 'b=1']], '/project')->command);
    }

    public function testTimeoutDefaultsToSixtySecondsWhenConstructed(): void
    {
        $config = new RunnerConfig('phpunit', ['php', 'vendor/bin/phpunit'], '/project');
        self::assertSame(60.0, $config->timeout);
    }

    #[DataProvider('providerFromRejectsInvalidEntries')]
    public function testFromRejectsInvalidEntries(mixed $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        RunnerConfig::from($value, '/project');
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerFromRejectsInvalidEntries(): array
    {
        $runner = ['extension' => 'phpunit', 'command' => ['phpunit']];
        $command = 'Runners require a nonempty command and positive timeout.';
        return [
            'not a mapping' => ['phpunit', 'runner must be a mapping.'],
            'unknown field' => [[...$runner, 'env' => []], "runner: unknown field 'env'."],
            'missing command' => [['extension' => 'phpunit'], $command],
            'empty command' => [[...$runner, 'command' => []], $command],
            'command string' => [[...$runner, 'command' => 'phpunit'], 'runner.command must be a list.'],
            'blank command entry' => [[...$runner, 'command' => ['php', ' ']], 'runner.command must contain nonempty strings.'],
            'zero timeout' => [[...$runner, 'timeout' => 0], $command],
            'negative timeout' => [[...$runner, 'timeout' => -1.5], $command],
            'string timeout' => [[...$runner, 'timeout' => '5'], $command],
            'infinite timeout' => [[...$runner, 'timeout' => INF], $command],
            'missing extension' => [['command' => ['phpunit']], 'extension must be a nonempty string.'],
            'blank cwd' => [[...$runner, 'cwd' => ''], 'cwd must be a nonempty string.'],
        ];
    }
}
