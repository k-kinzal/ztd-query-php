<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\VarcharLowering;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;

#[CoversClass(VarcharLowering::class)]
#[Small]
final class VarcharLoweringTest extends TestCase
{
    public function testLowerUsesTheExplicitCollationAndCharacterLength(): void
    {
        $type = new Character(CharacterKind::VarChar, '0032');
        $column = new Column(new Name('label'), $type, collation: new Name('UTF8MB4_BIN'));

        $native = (new VarcharLowering())->lower($column);

        self::assertSame(32, $native->length);
        self::assertSame('utf8mb4_bin', $native->collation->value);
    }
}
