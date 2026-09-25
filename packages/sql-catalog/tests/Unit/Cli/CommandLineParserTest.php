<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\CommandLine;
use SqlCatalog\Cli\CommandLineParser;
use SqlCatalog\Cli\InvalidCommandLineException;
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Filter\CatalogFilter;
use SqlCatalog\Core\Sql\StatementKind;

#[CoversClass(CommandLineParser::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(CatalogFilter::class)]
#[UsesClass(InvalidCommandLineException::class)]
#[UsesClass(\SqlCatalog\Facade\Configuration::class)]
#[UsesClass(\SqlCatalog\Facade\ConfigurationSchema::class)]
#[UsesClass(\SqlCatalog\Facade\InvalidConfigurationException::class)]
final class CommandLineParserTest extends TestCase
{
    public function testParseReadsPathsAndOptions(): void
    {
        $command = (new CommandLineParser())->parse(['--output', 'catalog', 'src', 'lib']);
        self::assertSame(['src', 'lib'], $command->paths);
        self::assertSame('catalog', $command->output);
    }

    public function testParseReadsAnOptionWrittenWithAnEqualsSign(): void
    {
        self::assertSame('catalog', (new CommandLineParser())->parse(['--output=catalog', 'src'])->output);
    }

    public function testParseReadsShortOptions(): void
    {
        $command = (new CommandLineParser())->parse(['-o', 'catalog', '-r', 'html', '-e', 'laravel', 'src']);
        self::assertSame('html', $command->reporter);
        self::assertSame(['laravel'], $command->extensions);
    }

    public function testParseSplitsARepeatableOptionOnCommas(): void
    {
        self::assertSame(['pdo', 'laravel'], (new CommandLineParser())->parse(['-e', 'pdo,laravel', 'src'])->extensions);
    }

    public function testParseAccumulatesARepeatedOption(): void
    {
        self::assertSame(['pdo', 'laravel'], (new CommandLineParser())->parse(['-e', 'pdo', '-e', 'laravel', 'src'])->extensions);
    }

    public function testParseReadsFlags(): void
    {
        self::assertTrue((new CommandLineParser())->parse(['--help'])->help);
        self::assertTrue((new CommandLineParser())->parse(['-h'])->help);
        self::assertTrue((new CommandLineParser())->parse(['--list-extensions'])->listExtensions);
        self::assertTrue((new CommandLineParser())->parse(['--list-reporters'])->listReporters);
    }

    public function testParseDefaultsToJsonWhenWritingIntoADirectory(): void
    {
        self::assertSame('json', (new CommandLineParser())->parse(['-o', 'catalog', 'src'])->reporter);
        self::assertSame('text', (new CommandLineParser())->parse(['src'])->reporter);
    }

    public function testParseBuildsTheFilterFromTheFilteringOptions(): void
    {
        $command = (new CommandLineParser())->parse([
            '--namespace=App', '--method=find', '--path=src/*', '--kind=select',
            '--table=users', '--sink=pdo.query', '--severity=high', 'src',
        ]);
        self::assertSame(['App'], $command->filter->namespaces);
        self::assertSame(['find'], $command->filter->functions);
        self::assertSame([StatementKind::Select], $command->filter->kinds);
        self::assertSame(Severity::High, $command->filter->minimumSeverity);
    }

    public function testParseTreatsADoubleDashAsAPath(): void
    {
        self::assertSame(['--'], (new CommandLineParser())->parse(['--'])->paths);
    }

    public function testParseRefusesAnUnknownOption(): void
    {
        $this->expectException(InvalidCommandLineException::class);
        $this->expectExceptionMessage('Unknown option "--nope".');
        (new CommandLineParser())->parse(['--nope', 'src']);
    }

    public function testParseRefusesAnOptionWithoutItsValue(): void
    {
        $this->expectException(InvalidCommandLineException::class);
        $this->expectExceptionMessage('Option "--output" needs a value.');
        (new CommandLineParser())->parse(['--output']);
    }

    public function testReadOptionConsumesTheArgumentThatFollows(): void
    {
        $values = [];
        $flags = [];
        $index = (new CommandLineParser())->readOption('--output', ['--output', 'catalog'], 0, $values, $flags);
        self::assertSame(1, $index);
        self::assertSame(['output' => ['catalog']], $values);
    }

    public function testBuildAppliesEveryDocumentedDefault(): void
    {
        $command = (new CommandLineParser())->build([], [], ['src']);

        self::assertSame(['src'], $command->paths);
        self::assertNull($command->output);
        self::assertSame('text', $command->reporter);
        self::assertSame(['pdo', 'mysqli'], $command->extensions);
        self::assertSame([], $command->excluded);
        self::assertSame('.', $command->root);
        self::assertNull($command->failOn);
        self::assertFalse($command->help);
        self::assertFalse($command->listExtensions);
        self::assertFalse($command->listReporters);
        self::assertTrue($command->filter->isEmpty());
    }

    public function testBuildTakesEveryOptionThatWasGiven(): void
    {
        $command = (new CommandLineParser())->build(
            [
                'output' => ['catalog'],
                'reporter' => ['html'],
                'extension' => ['laravel'],
                'exclude' => ['vendor'],
                'root' => ['/app'],
                'fail-on' => ['high'],
                'namespace' => ['App'],
                'method' => ['find'],
                'path' => ['src/*'],
                'kind' => ['select'],
                'table' => ['users'],
                'sink' => ['pdo.query'],
                'severity' => ['medium'],
            ],
            ['help' => true, 'list-extensions' => true, 'list-reporters' => true],
            ['src'],
        );

        self::assertSame('catalog', $command->output);
        self::assertSame('html', $command->reporter);
        self::assertSame(['laravel'], $command->extensions);
        self::assertSame(['vendor'], $command->excluded);
        self::assertSame('/app', $command->root);
        self::assertSame(Severity::High, $command->failOn);
        self::assertTrue($command->help);
        self::assertTrue($command->listExtensions);
        self::assertTrue($command->listReporters);
        self::assertSame(['App'], $command->filter->namespaces);
        self::assertSame(['find'], $command->filter->functions);
        self::assertSame(['src/*'], $command->filter->paths);
        self::assertSame(['users'], $command->filter->tables);
        self::assertSame(['pdo.query'], $command->filter->sinks);
        self::assertSame(Severity::Medium, $command->filter->minimumSeverity);
    }

    public function testBuildFallsBackToTheDefaults(): void
    {
        $command = (new CommandLineParser())->build([], [], ['src']);
        self::assertSame(['pdo', 'mysqli'], $command->extensions);
        self::assertSame('.', $command->root);
    }

    public function testFilterIsEmptyWithoutFilteringOptions(): void
    {
        self::assertTrue((new CommandLineParser())->filter([])->isEmpty());
    }

    public function testKindsReadsTheWrittenNames(): void
    {
        self::assertSame([StatementKind::Insert], (new CommandLineParser())->kinds(['INSERT']));
        self::assertSame([], (new CommandLineParser())->kinds([]));
    }

    public function testKindsRefusesAnUnknownName(): void
    {
        $this->expectException(InvalidCommandLineException::class);
        $this->expectExceptionMessage('Unknown statement kind "nope".');
        (new CommandLineParser())->kinds(['nope']);
    }

    public function testSeverityReadsTheWrittenName(): void
    {
        self::assertSame(Severity::Low, (new CommandLineParser())->severity(['fail-on' => ['LOW']], 'fail-on'));
        self::assertNull((new CommandLineParser())->severity([], 'fail-on'));
    }

    public function testSeverityRefusesAnUnknownName(): void
    {
        $this->expectException(InvalidCommandLineException::class);
        $this->expectExceptionMessage('Unknown severity "nope".');
        (new CommandLineParser())->severity(['severity' => ['nope']], 'severity');
    }

    public function testLastTakesTheValueGivenLast(): void
    {
        $parser = new CommandLineParser();
        self::assertSame('b', $parser->last(['output' => ['a', 'b']], 'output'));
        self::assertNull($parser->last([], 'output'));
    }
    public function testParseReadsAConfigurationPathWithoutSplittingCommas(): void
    {
        $path = sys_get_temp_dir() . '/catalog,local-' . bin2hex(random_bytes(6)) . '.yaml';
        file_put_contents($path, '{}');
        try {
            $parser = new CommandLineParser();
            self::assertSame($path, $parser->parse(['--config=' . $path, 'src'])->config);
            self::assertSame($path, $parser->parse(['-c', $path, 'src'])->config);
            self::assertNull($parser->parse(['src'])->config);
        } finally {
            unlink($path);
        }
    }

    public function testConfigurationDiscoversTheWorkingDirectoryFile(): void
    {
        $directory = sys_get_temp_dir() . '/catalog-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/.catalog.yaml', "paths: [src]\nexclude: [tests]\nreporter: html\noutput: catalog\n");
        $original = getcwd();
        self::assertNotFalse($original);
        chdir($directory);
        $absolute = getcwd();
        self::assertNotFalse($absolute);
        try {
            $parser = new CommandLineParser();
            $command = $parser->parse([]);
            self::assertSame([$absolute . '/src'], $command->paths);
            self::assertSame($absolute . '/catalog', $command->output);
            self::assertSame($absolute, $command->root);
            self::assertSame('html', $command->reporter);
            self::assertSame(['tests'], $command->excluded);
            $overridden = $parser->parse(['other', '--reporter=text', '--exclude=vendor', '--output=cli-output']);
            self::assertSame(['other'], $overridden->paths);
            self::assertSame('text', $overridden->reporter);
            self::assertSame(['vendor'], $overridden->excluded);
            self::assertSame('cli-output', $overridden->output);
        } finally {
            chdir($original);
            unlink($directory . '/.catalog.yaml');
            rmdir($directory);
        }
    }

    public function testConfigurationIsOptionalAndExplicitFilesMustExist(): void
    {
        self::assertNull((new CommandLineParser())->configuration(null)->file);
        $this->expectException(\SqlCatalog\Facade\InvalidConfigurationException::class);
        (new CommandLineParser())->configuration('/missing/.catalog.yaml');
    }

    public function testParseReadsTheLaravelDialectInBothOptionForms(): void
    {
        self::assertSame('sqlite', (new CommandLineParser())->parse(['--dialect=sqlite'])->dialect);
        self::assertSame('mysql', (new CommandLineParser())->parse(['--dialect', 'mysql'])->dialect);
    }

    public function testParseRejectsAnUnknownDialect(): void
    {
        $this->expectException(InvalidCommandLineException::class);
        (new CommandLineParser())->parse(['--dialect=unknown']);
    }
}
