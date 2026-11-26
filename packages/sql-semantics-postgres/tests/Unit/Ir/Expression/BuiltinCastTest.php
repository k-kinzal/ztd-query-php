<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;

#[CoversClass(BuiltinCast::class)]
#[Small]
final class BuiltinCastTest extends TestCase
{
    public function testRejectsAnInvalidEdgeEvenForTypedNull(): void
    {
        $this->expectExceptionMessage('no direct cast between bigint and boolean');

        new BuiltinCast(CastDestination::Boolean, new BuiltinCast(CastDestination::WideInteger, new NullLiteral()));
    }

    public function testKeepsAnExplicitTypedNullAcrossConversions(): void
    {
        $null = new NullLiteral();
        $inner = new BuiltinCast(CastDestination::Integer, $null);
        $outer = new BuiltinCast(CastDestination::Boolean, $inner);

        self::assertSame($inner, $outer->operand);
        self::assertSame($null, $inner->operand);
        self::assertSame(CastDestination::Boolean, $outer->target);
    }
}
