<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Values;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;

#[CoversClass(ValuesCell::class)]
#[Small]
final class ValuesCellTest extends TestCase
{
    public function testKindDistinguishesTypedFromUntypedNull(): void
    {
        $bare = new ValuesCell(new NullLiteral());
        $typed = new ValuesCell(new BuiltinCast(CastDestination::WideInteger, new NullLiteral()));

        self::assertNull($bare->kind());
        self::assertSame(ColumnKind::Int8, $typed->kind());
    }

    public function testToStringPreservesTheNullTypeCast(): void
    {
        $typed = new ValuesCell(new BuiltinCast(CastDestination::Boolean, new NullLiteral()));

        self::assertSame('CAST(NULL AS pg_catalog.bool)', $typed->toString());
    }
}
