<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Collation::class)]
#[Small]
final class CollationTest extends TestCase
{
    public function testNamedFindsOneObjectPerName(): void
    {
        self::assertSame(Collation::named('utf8mb3_general_ci'), Collation::named('UTF8_GENERAL_CI'));
        self::assertSame(33, Collation::named('utf8_general_ci')?->id);
        self::assertNull(Collation::named('klingon_ci'));
    }

    public function testKnownFindsANameTheCatalogHas(): void
    {
        self::assertSame(255, Collation::known('utf8mb4_0900_ai_ci')->id);
    }

    public function testBytesHoldsOnlyForTheBinaryCollation(): void
    {
        self::assertTrue(Collation::binary()->bytes());
        self::assertSame(63, Collation::binary()->id);
        self::assertFalse(Collation::known('utf8mb4_bin')->bytes());
    }

    public function testBinaryOrderCoversTheBinCollations(): void
    {
        self::assertTrue(Collation::binary()->binaryOrder());
        self::assertTrue(Collation::known('utf8mb4_0900_bin')->binaryOrder());
        self::assertFalse(Collation::known('utf8mb4_0900_as_cs')->binaryOrder());
    }

    public function testNamedReadsThePadAttributeOfTheServer(): void
    {
        self::assertFalse(Collation::known('utf8mb4_0900_ai_ci')->padSpace);
        self::assertTrue(Collation::known('utf8mb4_general_ci')->padSpace);
    }

    public function testAvailableInDependsOnTheRelease(): void
    {
        self::assertFalse(Collation::known('utf8mb4_0900_ai_ci')->availableIn(GrammarRelease::MySql5744));
        self::assertTrue(Collation::known('utf8mb4_0900_ai_ci')->availableIn(GrammarRelease::MySql8044));
    }

    public function testNameInSpellsUtf8mb3AsUtf8InFiveReleases(): void
    {
        self::assertSame('utf8_bin', Collation::known('utf8mb3_bin')->nameIn(GrammarRelease::MySql5744));
        self::assertSame('utf8mb3_bin', Collation::known('utf8mb3_bin')->nameIn(GrammarRelease::MySql901));
    }
}
