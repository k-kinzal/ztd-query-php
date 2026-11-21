<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Select;

#[CoversClass(Select::class)]
#[Small]
final class SelectTest extends TestCase
{
    public function testToStringRendersAnIndependentSnapshot(): void
    {
        $column = new ResultColumn(new IntegerLiteral('2'), new Identifier('a'));
        $select = new Select([&$column]);
        $column = new ResultColumn(new IntegerLiteral('3'), new Identifier('changed'));

        self::assertSame('SELECT 2 AS "a"', $select->toString());
        self::assertSame('changed', $column->name->value);
    }

    public function testProfileIdIdentifiesTheFixedRelease(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('a'))]);

        self::assertStringStartsWith('sqlite-3.47.2/', $select->profileId());
    }

    public function testRefusesUnboundColumnSlots(): void
    {
        $this->expectExceptionMessage('belong to the statement source');

        new Select([new ResultColumn(new ColumnReference(0), new Identifier('a'))]);
    }
}
