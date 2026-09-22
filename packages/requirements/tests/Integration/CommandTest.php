<?php

declare(strict_types=1);

namespace Requirements\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Requirements\Tests\Support\Workspace;
use Symfony\Component\Process\Process;

final class CommandTest extends TestCase
{
    public function testCliChecksCoverageFormatsAndFiltersIndependentSpecifications(): void
    {
        $workspace = new Workspace();
        $binary = dirname(__DIR__, 2) . '/bin/requirements';
        foreach (['lint', 'check', 'coverage', 'format'] as $command) {
            $process = new Process([PHP_BINARY, $binary, $command, '--json'], $workspace->directory);
            self::assertSame(0, $process->run(), $process->getOutput() . $process->getErrorOutput());
        }
        $format = new Process([PHP_BINARY, $binary, 'format', '--check'], $workspace->directory);
        self::assertSame(0, $format->run(), $format->getOutput());
        $gate = new Process([PHP_BINARY, $binary, 'coverage', '--min-coverage=100'], $workspace->directory);
        self::assertSame(1, $gate->run());
        $spec = new Process([PHP_BINARY, $binary, 'spec'], $workspace->directory);
        self::assertSame(1, $spec->run());
        self::assertStringContainsString('unverified', $spec->getOutput());
        $workspace->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'ORIGINAL-001', 'statement' => 'The parser shall reject truncated input.', 'origin' => 'original', 'reason' => 'Avoid silent data loss.', 'labels' => ['strictness']]]]);
        $list = new Process([PHP_BINARY, $binary, 'list', '--without-source', '--label=strictness', '--json'], $workspace->directory);
        self::assertSame(0, $list->run());
        self::assertStringContainsString('ORIGINAL-001', $list->getOutput());
        $empty = new Process([PHP_BINARY, $binary, 'spec', '--id=MISSING'], $workspace->directory);
        self::assertSame(1, $empty->run());
    }

    public function testMalformedConfigurationAndUnknownOptionsExitTwo(): void
    {
        $workspace = new Workspace();
        $binary = dirname(__DIR__, 2) . '/bin/requirements';
        $process = new Process([PHP_BINARY, $binary, 'coverage', '--min-coverage=not-a-number', '--json'], $workspace->directory);
        self::assertSame(2, $process->run());
        self::assertStringContainsString('percentage', $process->getOutput());
        $process = new Process([PHP_BINARY, $binary, 'list', '--unknown'], $workspace->directory);
        self::assertSame(2, $process->run());
    }
}
