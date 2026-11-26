<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind;

#[CoversClass(UnaryExpression::class)]
#[Small]
final class UnaryExpressionTest extends TestCase
{
    public function testPreservesTheBoundOperandAndItsPrefixRole(): void
    {
        $column = new ColumnReference(1);
        $expression = new UnaryExpression(UnaryKind::Plus, $column);

        self::assertSame($column, $expression->operand);
        self::assertSame(UnaryKind::Plus, $expression->kind);
    }
}
