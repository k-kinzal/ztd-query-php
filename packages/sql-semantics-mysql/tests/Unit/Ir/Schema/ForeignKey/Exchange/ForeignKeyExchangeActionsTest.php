<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ForeignKeyExchangeActions;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyExchangeActions::class)]
#[Small]
final class ForeignKeyExchangeActionsTest extends TestCase
{
    public function testCheckProjectsAnOriginalPhysicalDropAtItsWrittenPosition(): void
    {
        $table = NativeAdditionFixture::owner('child');
        $key = NativeAdditionFixture::key($table, 'previous', IndexRole::Ordinary, 'owner_id');
        $binding = new IndexRemovalTarget($key->identity, null);
        $edge = NativeAdditionFixture::addition('replacement');
        [$checked, $additions, , , $slots, $physical] = (new ForeignKeyExchangeActions())->check([$edge, $binding]);

        self::assertSame([$edge, $key->identity], $checked);
        self::assertSame([$edge], $additions);
        self::assertSame([0], $slots);
        self::assertSame([$binding], $physical);
    }
}
