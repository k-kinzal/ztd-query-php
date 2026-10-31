<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;

#[CoversClass(\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand::class)]
#[Medium]
final class RowOperandTest extends TestCase
{
    public function testRowsAreTypedThroughTheirOperandRole(): void
    {
        $row = new Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $derivation = new \SqlSemantics\Construction\Derivation((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context([]));
        $fact = $derivation->scalar($row, $derivation->environment());

        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $fact->type);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Tuple(2), $fact->type->descriptor);
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
