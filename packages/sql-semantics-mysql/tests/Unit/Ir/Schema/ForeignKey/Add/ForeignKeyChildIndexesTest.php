<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyChildIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyIndexPrefix;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
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
        [$keys] = (new ForeignKeyChildIndexes())->allocate(
            $owner,
            [],
            [$pairs, $pairs],
            [new Identifier('Early'), new Identifier('Later')],
            GrammarRelease::MySql910
        );

        self::assertNull($keys[0]);
        self::assertSame('Later', $keys[1]?->value);
    }

    public function testReplacedRequiresImplicitOriginAndTheCompleteDirectionalPrefix(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $explicit = NativeAdditionFixture::key($owner, 'Explicit', IndexRole::Ordinary, 'owner_id');
        $initial = NativeAdditionFixture::key($owner, 'Implicit', IndexRole::Ordinary, 'owner_id');
        $implicit = new ForeignKeyNativeKey($initial->identity, $initial->fields, true, implicitForeignKey: true);
        $replaced = (new ForeignKeyChildIndexes())->replaced(
            ['explicit' => $explicit, 'implicit' => $implicit],
            [[['owner_id', false], ['other', false]]],
            new ForeignKeyIndexPrefix(),
        );

        self::assertSame(['implicit' => $initial->identity->name], $replaced);
    }

    public function testCoveredPrefersTheNewEqualImplicitKeyAndKeepsLongerSupport(): void
    {
        $keys = new ForeignKeyChildIndexes();
        $proposed = [[['owner_id', false]]];

        self::assertFalse($keys->covered(0, $proposed, [[[['owner_id', false]], true]]));
        self::assertTrue($keys->covered(0, $proposed, [[[['owner_id', false], ['code', true]], true]]));
    }

    public function testCoveredUsesExactNativeDirection(): void
    {
        $keys = new ForeignKeyChildIndexes();

        self::assertFalse($keys->covered(0, [[['owner_id', true]]], [[[['owner_id', false]], false]]));
        self::assertTrue($keys->covered(0, [[['owner_id', true]]], [[[['owner_id', true]], false]]));
    }
}
