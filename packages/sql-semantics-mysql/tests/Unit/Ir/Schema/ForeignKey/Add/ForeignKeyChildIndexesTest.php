<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyChildIndexes;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyChildIndexes::class)]
#[Small]
final class ForeignKeyChildIndexesTest extends TestCase
{
    public function testAllocateUsesTheLaterEqualCandidate(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $pairs = [NativeAdditionFixture::pair('owner_id', 'id')];
        $keys = (new ForeignKeyChildIndexes())->allocate(
            $owner,
            [],
            [$pairs, $pairs],
            [new Identifier('Early'), new Identifier('Later')],
            GrammarRelease::MySql910
        );

        self::assertNull($keys[0]);
        self::assertSame('Later', $keys[1]?->value);
    }

    public function testCoveredUsesExactNativeDirection(): void
    {
        $keys = new ForeignKeyChildIndexes();

        self::assertFalse($keys->covered(0, [[['owner_id', true]]], [[['owner_id', false]]]));
        self::assertTrue($keys->covered(0, [[['owner_id', true]]], [[['owner_id', true]]]));
    }
}
