<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(RowSubquery::class)]
#[Medium]
final class RowSubqueryTest extends TestCase
{
    public function testDeriveScalarRetainsTheMultiColumnFact(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $context = $platform->context($platform->profile('mysql-8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('1')), new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('2'))]);
        $fact = $derivation->scalar(new RowSubquery($query), $derivation->environment());
        self::assertNull($fact->replacement);
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $fact->type);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Tuple(2), $fact->type->descriptor);
    }

    public function testRenderKeepsTheQueryAsARowOperand(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (1,2)=(SELECT 1,2)');
        self::assertSame('SELECT (1,2)=(SELECT 1,2)', $operation->toString());
    }

}
