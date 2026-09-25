<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Extension\ExtensionInterface;
use SqlCatalog\Core\Extension\ExtensionRegistry;
use SqlCatalog\Core\Extension\SinkSpec;
use SqlCatalog\Core\Extension\UnknownExtensionException;
use SqlCatalog\Extension\Doctrine\DoctrineExtension;
use SqlCatalog\Extension\Mysqli\MysqliExtension;
use SqlCatalog\Extension\Pdo\PdoExtension;
use SqlCatalog\Extension\WordPress\WordPressExtension;
use SqlCatalog\Facade\LaravelExtension;

#[CoversClass(ExtensionRegistry::class)]
#[UsesClass(DoctrineExtension::class)]
#[UsesClass(LaravelExtension::class)]
#[UsesClass(MysqliExtension::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(WordPressExtension::class)]
#[UsesClass(SinkSpec::class)]
#[UsesClass(UnknownExtensionException::class)]
final class ExtensionRegistryTest extends TestCase
{
    public function testWithBuiltinsRegistersEverythingThatShips(): void
    {
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo', 'wordpress'], \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->names());
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
        self::assertSame(['pdo', 'mysqli'], \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->defaultNames());
        self::assertSame(['pdo'], (new ExtensionRegistry([new PdoExtension()], ['pdo']))->defaultNames());
    }

    public function testSinksOfCollectsTheCallsOfEveryNamedExtension(): void
    {
        $sinks = \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->sinksOf(['pdo', 'mysqli']);
        $ids = array_map(static fn (SinkSpec $sink): string => $sink->id, $sinks);
        self::assertContains('pdo.query', $ids);
        self::assertContains('mysqli.query', $ids);
    }

    public function testSinksOfRefusesAnUnknownName(): void
    {
        $this->expectException(UnknownExtensionException::class);
        \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->sinksOf(['symfony']);
    }

    public function testAllReturnsTheExtensionsAlphabetically(): void
    {
        $names = array_map(
            static fn (ExtensionInterface $extension): string => $extension->name(),
            \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->all(),
        );
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo', 'wordpress'], $names);
    }

    public function testGlobalsOfCollectsWhatTheNamedExtensionsDeclare(): void
    {
        self::assertSame(
            ['wpdb' => 'wpdb'],
            \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->globalsOf(['pdo', 'wordpress']),
        );
    }

    public function testGlobalsOfRefusesAnUnknownName(): void
    {
        $this->expectException(UnknownExtensionException::class);
        \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->globalsOf(['symfony']);
    }
    public function testModelProvidersOfSelectsEnabledProvidersAndKeepsRawExtensionsCompatible(): void
    {
        $registry = \SqlCatalog\Facade\ExtensionRegistry::withBuiltins();
        self::assertSame([], $registry->modelProvidersOf(['pdo', 'wordpress']));
        self::assertSame([$registry->get('laravel')], $registry->modelProvidersOf(['pdo', 'laravel']));
    }

}
