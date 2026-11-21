<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;

#[CoversClass(ValueRow::class)]
#[Small]
final class ValueRowTest extends TestCase
{
    public function testToStringKeepsParameterLabelsAndPositions(): void
    {
        $row = new ValueRow([new Parameter('02'), new IntegerLiteral('3'), new Parameter('2')]);

        self::assertSame('(?02, 3, ?2)', $row->toString());
    }
}
