<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithDefinition;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\PostgreSql\Ir\Select;

#[CoversClass(WithProjection::class)]
#[Small]
final class WithProjectionTest extends TestCase
{
    public function testWidthReturnsTheFinalProjectionWidth(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);
        $query = new WithProjection([new WithDefinition($body)], $read);

        self::assertSame(1, $query->width());
    }

    public function testProfileIdMatchesTheOwnedSelect(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);
        $query = new WithProjection([new WithDefinition($body)], $read);

        self::assertSame($body->profileId(), $query->profileId());
    }

    public function testToStringUsesTheExactLocalSlot(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);
        $query = new WithProjection([new WithDefinition($body)], $read);

        self::assertStringContainsString('SELECT "__ztd_cte_0"."__ztd_c0" AS "result"', $query->toString());
    }
}
