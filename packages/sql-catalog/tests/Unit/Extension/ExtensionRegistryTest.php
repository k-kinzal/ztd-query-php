<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\DoctrineExtension;
use SqlCatalog\Extension\ExtensionInterface;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Extension\LaravelExtension;
use SqlCatalog\Extension\MysqliExtension;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Extension\UnknownExtensionException;

#[CoversClass(ExtensionRegistry::class)]
#[UsesClass(DoctrineExtension::class)]
#[UsesClass(LaravelExtension::class)]
#[UsesClass(MysqliExtension::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(SinkSpec::class)]
#[UsesClass(UnknownExtensionException::class)]
final class ExtensionRegistryTest extends TestCase
{
    public function testWithBuiltinsRegistersEverythingThatShips(): void
    {
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo'], ExtensionRegistry::withBuiltins()->names());
    }

    public function testRegisterReplacesAnExtensionOfTheSameName(): void
    {
        $registry = new ExtensionRegistry([new PdoExtension()]);
        $registry->register(new PdoExtension());
        self::assertSame(['pdo'], $registry->names());
    }

    public function testNamesAreAlphabetical(): void
    {
        self::assertSame(['mysqli', 'pdo'], (new ExtensionRegistry([new PdoExtension(), new MysqliExtension()]))->names());
    }

    public function testHasReportsWhetherAnExtensionIsRegistered(): void
    {
        $registry = new ExtensionRegistry([new PdoExtension()]);
        self::assertTrue($registry->has('pdo'));
        self::assertFalse($registry->has('laravel'));
    }

    public function testGetAnswersWithTheRegisteredExtension(): void
    {
        self::assertSame('pdo', (new ExtensionRegistry([new PdoExtension()]))->get('pdo')->name());
    }

    public function testGetRefusesAnUnknownName(): void
    {
        $this->expectException(UnknownExtensionException::class);
        (new ExtensionRegistry([new PdoExtension()]))->get('symfony');
    }

    public function testDefaultNamesAreTheDriverExtensionsThatAreRegistered(): void
    {
        self::assertSame(['pdo', 'mysqli'], ExtensionRegistry::withBuiltins()->defaultNames());
        self::assertSame(['pdo'], (new ExtensionRegistry([new PdoExtension()]))->defaultNames());
    }

    public function testSinksOfCollectsTheCallsOfEveryNamedExtension(): void
    {
        $sinks = ExtensionRegistry::withBuiltins()->sinksOf(['pdo', 'mysqli']);
        $ids = array_map(static fn (SinkSpec $sink): string => $sink->id, $sinks);
        self::assertContains('pdo.query', $ids);
        self::assertContains('mysqli.query', $ids);
    }

    public function testSinksOfRefusesAnUnknownName(): void
    {
        $this->expectException(UnknownExtensionException::class);
        ExtensionRegistry::withBuiltins()->sinksOf(['symfony']);
    }

    public function testAllReturnsTheExtensionsAlphabetically(): void
    {
        $names = array_map(
            static fn (ExtensionInterface $extension): string => $extension->name(),
            ExtensionRegistry::withBuiltins()->all(),
        );
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo'], $names);
    }
}
