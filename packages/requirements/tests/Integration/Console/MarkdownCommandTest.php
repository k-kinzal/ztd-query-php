<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\MarkdownDocument;
use Requirements\Console\Application;
use Requirements\Console\CommandHandler;
use Requirements\Console\CommandLine;
use Requirements\Console\Executor;
use Requirements\Console\Formatter;
use Symfony\Component\Process\Process;
use Tests\Fake\PhpUnitSuite;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Application::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(CommandHandler::class)]
#[UsesClass(Executor::class)]
#[UsesClass(Formatter::class)]
#[UsesClass(MarkdownDocument::class)]
#[Medium]
final class MarkdownCommandTest extends TestCase
{
    #[DataProvider('providerCommands')]
    public function testRunChecksMarkdownAndRunsItsTestWithoutAnExternalMarkdownTool(string $command): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true], 'runners' => ['unit' => ['extension' => 'phpunit', 'command' => PhpUnitSuite::write($project->directory)]]]);
        $project->put('definition.md', <<<'MD'
---
version: 1
source: null
---

# ORIGINAL-001

The converter shall uppercase letters.

**origin**

original

**reason**

Demonstrate the runner contract.

**tests**

- **unit:** Sample\PassingTest::testPass
MD);
        $process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/bin/requirements', $command, '--json'], $project->directory, ['PATH' => '/nonexistent', 'SHELL_VERBOSITY' => false]);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('"passed": true', $process->getOutput());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerCommands(): array
    {
        return [
            'lint' => ['lint'],
            'check' => ['check'],
            'spec' => ['spec'],
            'format' => ['format'],
        ];
    }
}
