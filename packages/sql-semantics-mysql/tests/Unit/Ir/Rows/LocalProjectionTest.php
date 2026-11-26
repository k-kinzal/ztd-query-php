<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(LocalProjection::class)]
#[Small]
final class LocalProjectionTest extends TestCase
{
    public function testProfileIdComesFromTheDefinition(): void
    {
        $name = new Identifier('a');
        $select = new Select([new ResultColumn(new IntegerLiteral('1'), $name), new ResultColumn(new IntegerLiteral('2'), $name)]);
        $local = new LocalProjection($select, [new LocalResultColumn(1, $name)]);

        self::assertSame($select->profileId(), $local->profileId());
    }

    public function testToStringUsesTheDefinedOrdinalAndUniqueInnerNames(): void
    {
        $name = new Identifier('a');
        $select = new Select([new ResultColumn(new IntegerLiteral('1'), $name), new ResultColumn(new IntegerLiteral('2'), $name)]);
        $local = new LocalProjection($select, [new LocalResultColumn(1, $name)]);

        self::assertStringStartsWith('SELECT `__ztd_local`.`__ztd_c1` AS `a`', $local->toString());
        self::assertStringContainsString(' AS `__ztd_c0`', $local->toString());
    }
}
