<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Positions::class)]
#[Medium]
final class PositionsTest extends TestCase
{
    public function testValueFoldsMinusSignsAndParentheses(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 ORDER BY - (- 1)');
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $position = $select->options?->order[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $position);
        self::assertSame('1', (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Positions())->value($position->position));
    }

    public function testMisusedTellsANonIntegerConstant(): void
    {
        self::assertSame([true, false], [(new \SqlSemantics\Platform\PostgreSql\Rules\Query\Positions())->misused(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()), (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Positions())->misused(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]);
    }

    public function testMinusTellsThePlainMinusSign(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Rules\Query\Positions())->minus(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('-')), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))));
    }
}
