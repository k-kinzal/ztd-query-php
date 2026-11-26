<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Values;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;

#[CoversClass(ValuesRow::class)]
#[Small]
final class ValuesRowTest extends TestCase
{
    public function testToStringPreservesTheDistinctOrderedCells(): void
    {
        $row = new ValuesRow([new ValuesCell(new BooleanLiteral(false)), new ValuesCell(new NullLiteral())]);

        self::assertSame('(FALSE, NULL)', $row->toString());
    }
}
