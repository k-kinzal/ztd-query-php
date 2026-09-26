<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\CommandHandler;
use Requirements\Console\CommandLine;
use Requirements\Console\Options;
use Requirements\Console\Overview;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(Overview::class)]
#[UsesClass(CommandHandler::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(Options::class)]
#[Small]
final class OverviewTest extends TestCase
{
    /**
     * @param list<string> $arguments
     */
    #[DataProvider('providerRequested')]
    public function testRequestedTellsWhetherTheOverviewIsAskedFor(array $arguments, bool $expected): void
    {
        self::assertSame($expected, (new Overview())->requested((new CommandLine())->create(), new ArgvInput(['requirements', ...$arguments])));
    }

    /**
     * @return array<string, array{list<string>, bool}>
     */
    public static function providerRequested(): array
    {
        return [
            'nothing' => [[], true],
            'help option' => [['--help'], true],
            'help shortcut' => [['-h'], true],
            'help option without decoration' => [['--help', '--no-ansi'], true],
            'help with a missing configuration' => [['--config', 'missing.yaml', '--help'], true],
            'help with json' => [['--json', '--help'], true],
            'help command' => [['help'], true],
            'help for a command' => [['help', 'coverage'], false],
            'quiet' => [['--quiet'], true],
            'command' => [['lint'], false],
            'command help' => [['coverage', '--help'], false],
            'command help shortcut' => [['spec', '-h'], false],
            'list' => [['list'], false],
            'unknown command' => [['unknown'], false],
            'version' => [['--version'], false],
            'version shortcut' => [['-V'], false],
            'help with version' => [['help', '--version'], false],
            'unknown option' => [['--unknown'], false],
        ];
    }

    public function testRequestedLeavesTheInputUnbound(): void
    {
        $input = new ArgvInput(['requirements', 'lint']);
        (new Overview())->requested((new CommandLine())->create(), $input);
        self::assertSame([], $input->getArguments());
    }

    public function testShowDescribesTheApplication(): void
    {
        $output = new BufferedOutput();
        (new Overview())->show((new CommandLine())->create(), $output, []);
        $text = $output->fetch();
        self::assertStringContainsString('Usage:', $text);
        self::assertStringContainsString('Available commands:', $text);
        self::assertStringContainsString('Show source coverage and enforce total and differential gates.', $text);
        self::assertStringContainsString('--config', $text);
        self::assertStringNotContainsString("\x1b", $text);
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('providerShowDecoration')]
    public function testShowHonorsTheDecorationOptions(bool $decorated, array $arguments, bool $expected): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, $decorated);
        (new Overview())->show((new CommandLine())->create(), $output, $arguments);
        self::assertSame($expected, $output->isDecorated());
        self::assertSame($expected, str_contains($output->fetch(), "\x1b["));
    }

    /**
     * @return array<string, array{bool, list<string>, bool}>
     */
    public static function providerShowDecoration(): array
    {
        return [
            'plain stays plain' => [false, [], false],
            'decorated stays decorated' => [true, [], true],
            'no ansi' => [true, ['--no-ansi'], false],
            'ansi' => [false, ['--ansi'], true],
            'ansi wins over no ansi' => [false, ['--no-ansi', '--ansi'], true],
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('providerShowQuiet')]
    public function testShowHonorsQuiet(array $arguments): void
    {
        $output = new BufferedOutput();
        (new Overview())->show((new CommandLine())->create(), $output, $arguments);
        self::assertSame(OutputInterface::VERBOSITY_QUIET, $output->getVerbosity());
        self::assertSame('', $output->fetch());
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function providerShowQuiet(): array
    {
        return [
            'long' => [['--quiet']],
            'short' => [['-q']],
            'with help' => [['--help', '--quiet']],
        ];
    }
}
