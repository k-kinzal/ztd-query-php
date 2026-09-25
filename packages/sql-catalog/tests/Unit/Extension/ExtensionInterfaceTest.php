<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\DoctrineExtension;
use SqlCatalog\Extension\ExtensionInterface;
use SqlCatalog\Extension\LaravelExtension;
use SqlCatalog\Extension\MysqliExtension;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\SinkSpec;

#[CoversClass(ExtensionInterface::class)]
#[UsesClass(DoctrineExtension::class)]
#[UsesClass(LaravelExtension::class)]
#[UsesClass(MysqliExtension::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(SinkSpec::class)]
final class ExtensionInterfaceTest extends TestCase
{
    public function testNameIsUniqueAcrossTheExtensionsThatShip(): void
    {
        $names = array_map(
            static fn (ExtensionInterface $extension): string => $extension->name(),
            [new PdoExtension(), new MysqliExtension(), new DoctrineExtension(), new LaravelExtension()],
        );
        self::assertSame($names, array_values(array_unique($names)));
    }

    public function testDescriptionIsAlwaysWritten(): void
    {
        $descriptions = array_map(
            static fn (ExtensionInterface $extension): string => $extension->description(),
            [new PdoExtension(), new MysqliExtension(), new DoctrineExtension(), new LaravelExtension()],
        );
        self::assertNotContains('', $descriptions);
    }

    public function testSinksAreIdentifiedUniquelyWithinAnExtension(): void
    {
        $ids = array_map(static fn (SinkSpec $sink): string => $sink->id, (new PdoExtension())->sinks());
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    public function testGlobalsAreDeclaredOnlyByAnExtensionThatHandsOneOut(): void
    {
        $globals = array_map(
            static fn (ExtensionInterface $extension): array => $extension->globals(),
            [new PdoExtension(), new MysqliExtension(), new DoctrineExtension(), new LaravelExtension()],
        );
        self::assertSame([[], [], [], []], $globals);
    }
}
