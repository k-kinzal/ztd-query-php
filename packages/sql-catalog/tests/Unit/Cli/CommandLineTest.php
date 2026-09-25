<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\CommandLine;
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Filter\CatalogFilter;

#[CoversClass(CommandLine::class)]
#[UsesClass(CatalogFilter::class)]
#[UsesClass(\SqlCatalog\Facade\Configuration::class)]
final class CommandLineTest extends TestCase
{
    public function testIsQueryWhenTheCommandWasAskedForInformation(): void
    {
        self::assertTrue((new CommandLine(help: true))->isQuery());
        self::assertTrue((new CommandLine(listExtensions: true))->isQuery());
        self::assertTrue((new CommandLine(listReporters: true))->isQuery());
        self::assertFalse((new CommandLine(['src']))->isQuery());
    }

    public function testTheDefaultsAreTheOnesTheHelpDocuments(): void
    {
        $command = new CommandLine();
        self::assertSame('text', $command->reporter);
        self::assertSame(['pdo', 'mysqli'], $command->extensions);
        self::assertSame('.', $command->root);
        self::assertNull($command->output);
        self::assertNull($command->failOn);
        self::assertTrue($command->filter->isEmpty());
    }

    public function testWhatWasAskedForIsKept(): void
    {
        $command = new CommandLine(
            ['src'],
            'catalog',
            'json',
            ['laravel'],
            new CatalogFilter(['App']),
            ['vendor'],
            'root',
            Severity::High,
        );
        self::assertSame(['src'], $command->paths);
        self::assertSame('catalog', $command->output);
        self::assertSame(['vendor'], $command->excluded);
        self::assertSame(Severity::High, $command->failOn);
    }
}
