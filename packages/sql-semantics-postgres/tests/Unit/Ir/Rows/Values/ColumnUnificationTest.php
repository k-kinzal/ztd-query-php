<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Values;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ColumnUnification;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;

#[CoversClass(ColumnUnification::class)]
#[Small]
final class ColumnUnificationTest extends TestCase
{
    public function testOfResolvesMixedSignedWidthsInBothRowOrders(): void
    {
        $narrow = new ValuesRow([new ValuesCell(new IntegerLiteral('1'))]);
        $wide = new ValuesRow([new ValuesCell(new WideIntegerLiteral('4000000000'))]);
        $type = new ColumnUnification();

        self::assertSame(ColumnKind::Int8, $type->of([$narrow, $wide], 0));
        self::assertSame(ColumnKind::Int8, $type->of([$wide, $narrow], 0));
    }
}
