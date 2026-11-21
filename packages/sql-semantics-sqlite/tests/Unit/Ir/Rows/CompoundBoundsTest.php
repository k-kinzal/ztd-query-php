<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundBounds;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundStep;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;

#[CoversClass(CompoundBounds::class)]
#[Small]
final class CompoundBoundsTest extends TestCase
{
    public function testCheckRejectsOuterOrderingAfterValues(): void
    {
        $arm = new ValuesQuery([new ValueRow([new IntegerLiteral('1')])]);
        $step = new CompoundStep(CompoundOperator::Union, $arm);

        $this->expectExceptionMessage('requires a final SELECT');
        (new CompoundBounds())->check($arm, [$step], [new Order(1)], null);
    }
}
