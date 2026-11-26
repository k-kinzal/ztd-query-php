<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\Compound;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundStep;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CompoundViewBounds;

#[CoversClass(CompoundViewBounds::class)]
#[Small]
final class CompoundViewBoundsTest extends TestCase
{
    public function testCheckAdmitsAnUntypedCompoundWithAHeader(): void
    {
        $values = new ValuesQuery([new ValueRow([new IntegerLiteral('7')])]);
        $query = new Compound($values, [new CompoundStep(CompoundOperator::UnionAll, $values)]);

        (new CompoundViewBounds())->check(new Identifier('main'), $query, 1, 44);

        self::assertSame('VALUES (7) UNION ALL VALUES (7)', $query->toString());
    }

    public function testTypesAcceptsAnIntegerValueWithNoDeclaredType(): void
    {
        $values = new ValuesQuery([new ValueRow([new IntegerLiteral('7')])]);

        (new CompoundViewBounds())->types($values);

        self::assertCount(1, $values->rows);
    }
}
