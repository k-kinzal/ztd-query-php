<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Window\Direction;
use SqlSemantics\Model\Window\Offset;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Offset::class)]
#[Medium]
final class OffsetTest extends TestCase
{
    public function testExposesTheOffsetValueAsItsOnlyExpression(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL)'));
        $query = $binder->bind('SELECT sum(id) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        $end = $call->window->frame?->end;
        self::assertInstanceOf(Offset::class, $end);
        self::assertSame(Direction::Following, $end->direction);
        self::assertSame('1', $end->value->spelling());
        self::assertSame([$end->value], $end->expressions());
        self::assertSame('SELECT sum("id") OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM "main"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testExpressionsContainOnlyTheOffsetValue(): void
    {
        $value = Expression::literal(3, Dialect::PostgreSql);
        $offset = new Offset(Direction::Preceding, $value);
        self::assertSame(Direction::Preceding, $offset->direction);
        self::assertSame($value, $offset->value);
        self::assertSame([$value], $offset->expressions());
    }
}
