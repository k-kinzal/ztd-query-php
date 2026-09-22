<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\HtmlReporter;
use SqlCatalog\Reporter\JsonReporter;
use SqlCatalog\Reporter\ReporterInterface;
use SqlCatalog\Reporter\ReporterRegistry;
use SqlCatalog\Reporter\TextReporter;
use SqlCatalog\Reporter\UnknownReporterException;

#[CoversClass(ReporterRegistry::class)]
#[UsesClass(HtmlReporter::class)]
#[UsesClass(JsonReporter::class)]
#[UsesClass(TextReporter::class)]
#[UsesClass(UnknownReporterException::class)]
final class ReporterRegistryTest extends TestCase
{
    public function testWithBuiltinsRegistersEverythingThatShips(): void
    {
        self::assertSame(['html', 'json', 'text'], ReporterRegistry::withBuiltins()->names());
    }

    public function testRegisterReplacesAReporterOfTheSameName(): void
    {
        $registry = new ReporterRegistry([new JsonReporter()]);
        $registry->register(new JsonReporter());
        self::assertSame(['json'], $registry->names());
    }

    public function testNamesAreAlphabetical(): void
    {
        self::assertSame(['json', 'text'], (new ReporterRegistry([new TextReporter(), new JsonReporter()]))->names());
    }

    public function testHasReportsWhetherAReporterIsRegistered(): void
    {
        $registry = new ReporterRegistry([new JsonReporter()]);
        self::assertTrue($registry->has('json'));
        self::assertFalse($registry->has('html'));
    }

    public function testGetAnswersWithTheRegisteredReporter(): void
    {
        self::assertSame('json', (new ReporterRegistry([new JsonReporter()]))->get('json')->name());
    }

    public function testGetRefusesAnUnknownName(): void
    {
        $this->expectException(UnknownReporterException::class);
        (new ReporterRegistry([new JsonReporter()]))->get('xml');
    }

    public function testAllReturnsTheReportersAlphabetically(): void
    {
        $names = array_map(
            static fn (ReporterInterface $reporter): string => $reporter->name(),
            ReporterRegistry::withBuiltins()->all(),
        );
        self::assertSame(['html', 'json', 'text'], $names);
    }
}
