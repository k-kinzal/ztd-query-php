<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\Workspace;

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
        $list = new Process([PHP_BINARY, $binary, 'spec', '--no-test', '--without-source', '--label=strictness', '--json'], $workspace->directory);
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
        $process = new Process([PHP_BINARY, $binary, 'spec', '--unknown'], $workspace->directory);
        self::assertSame(2, $process->run());
    }

    public function testHelpWorksWithoutAProjectAndIncludesCommandSpecificOptions(): void
    {
        $workspace = new Workspace();
        unlink($workspace->directory . '/requirements.yaml');
        $binary = dirname(__DIR__, 2) . '/bin/requirements';
        foreach ([[], ['--help'], ['-h'], ['--help', '--no-ansi'], ['--config', 'missing.yaml', '--help'], ['--json', '--help'], ['help', 'coverage'], ['coverage', '--help'], ['spec', '-h'], ['list']] as $arguments) {
            $process = new Process([PHP_BINARY, $binary, ...$arguments], $workspace->directory);
            self::assertSame(0, $process->run(), $process->getErrorOutput());
            self::assertStringContainsString('Usage:', $process->getOutput());
            self::assertStringNotContainsString("\x1b", $process->getOutput());
        }
        $overview = new Process([PHP_BINARY, $binary, '--config', 'missing.yaml', '--help'], $workspace->directory);
        self::assertSame(0, $overview->run());
        self::assertStringContainsString('Available commands:', $overview->getOutput());
        $help = new Process([PHP_BINARY, $binary, 'coverage', '--help'], $workspace->directory);
        self::assertSame(0, $help->run());
        self::assertStringContainsString('--min-diff-coverage', $help->getOutput());
        self::assertStringContainsString('--snapshot', $help->getOutput());
        self::assertStringContainsString('--write-snapshot', $help->getOutput());
        self::assertStringNotContainsString('baseline', $help->getOutput());
        self::assertStringNotContainsString('--without-source', $help->getOutput());
        $help = new Process([PHP_BINARY, $binary, 'spec', '--help'], $workspace->directory);
        self::assertSame(0, $help->run());
        self::assertStringContainsString('--no-test', $help->getOutput());
        self::assertStringContainsString('--without-source', $help->getOutput());
    }

    public function testGlobalOptionsBeforeCommandAndHumanReadableTables(): void
    {
        $workspace = new Workspace();
        $binary = dirname(__DIR__, 2) . '/bin/requirements';
        $process = new Process([PHP_BINARY, $binary, '--config', 'requirements.yaml', 'coverage', '--no-ansi'], $workspace->directory);
        self::assertSame(0, $process->run(), $process->getErrorOutput());
        self::assertStringContainsString('33.33%', $process->getOutput());
        self::assertStringContainsString('Uncovered', $process->getOutput());
        self::assertStringNotContainsString('passed:', $process->getOutput());
        self::assertStringNotContainsString('{  }', $process->getOutput());
        $list = new Process([PHP_BINARY, $binary, 'spec', '--no-test', '--no-ansi'], $workspace->directory);
        self::assertSame(0, $list->run());
        self::assertStringContainsString('SPEC-001', $list->getOutput());
        self::assertStringContainsString('Statement', $list->getOutput());
    }

    public function testCliWritesAndComparesCoverageSnapshots(): void
    {
        $workspace = new Workspace();
        $binary = dirname(__DIR__, 2) . '/bin/requirements';
        $write = new Process([PHP_BINARY, $binary, 'coverage', '--write-snapshot=snapshot.json', '--json'], $workspace->directory);
        self::assertSame(0, $write->run(), $write->getOutput() . $write->getErrorOutput());
        $snapshot = json_decode((string) file_get_contents($workspace->directory . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);
        self::assertSame('requirements-snapshot', $snapshot['type']);
        self::assertSame(['version', 'type', 'units'], array_keys($snapshot));
        $compare = new Process([PHP_BINARY, $binary, 'coverage', '--snapshot=snapshot.json', '--min-diff-coverage=100', '--json'], $workspace->directory);
        self::assertSame(0, $compare->run(), $compare->getOutput() . $compare->getErrorOutput());
        $report = json_decode($compare->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame(['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null], $report['diff']);
        self::assertSame([], $report['removed']);
        $missing = new Process([PHP_BINARY, $binary, 'coverage', '--min-diff-coverage=100', '--no-ansi'], $workspace->directory);
        self::assertSame(1, $missing->run());
        self::assertStringContainsString('requires --snapshot', $missing->getOutput());
    }

    public function testInvalidOptionsAndMissingConfigurationKeepJsonMachineReadable(): void
    {
        $workspace = new Workspace();
        $binary = dirname(__DIR__, 2) . '/bin/requirements';
        foreach ([['coverage', '--unknown'], ['coverage', '--baseline=snapshot.json'], ['coverage', '--write-baseline=snapshot.json'], ['spec', '--live'], ['unknown'], ['--config', 'missing.yaml', 'lint']] as $arguments) {
            $process = new Process([PHP_BINARY, $binary, '--json', ...$arguments], $workspace->directory);
            self::assertSame(2, $process->run());
            $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($report);
            self::assertFalse($report['passed']);
            self::assertSame('', $process->getErrorOutput());
        }
        $process = new Process([PHP_BINARY, $binary, 'lint', '--config', 'missing.yaml', '--no-ansi'], $workspace->directory);
        self::assertSame(2, $process->run());
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('[ERROR]', $process->getErrorOutput());
        self::assertStringContainsString('Configuration does not exist', $process->getErrorOutput());
    }

}
