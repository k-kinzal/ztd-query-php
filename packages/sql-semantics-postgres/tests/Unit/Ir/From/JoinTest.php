<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(Join::class)]
#[Small]
final class JoinTest extends TestCase
{
    public function testInputsKeepOrientationsAndRepeatedTables(): void
    {
        $a = new TableSource(new Identifier('public'), new Identifier('a'), []);
        $b = new TableSource(new Identifier('public'), new Identifier('b'), []);
        $nested = new Join(JoinKind::Cross, $b, $a);
        $join = new Join(JoinKind::Cross, $a, $nested);

        $snapshot = $join->inputs();
        array_pop($snapshot);

        self::assertSame([$a, $b, $a], $join->inputs());
    }

    public function testCrossRejectsAnAttachedPredicate(): void
    {
        $table = new TableSource(new Identifier('public'), new Identifier('t'), []);
        $this->expectExceptionMessage('Only CROSS has no join predicate.');

        new Join(JoinKind::Cross, $table, $table, new BooleanLiteral(true));
    }

    public function testFullAcceptsAnOrderedConjunctionOfCrossSideEqualities(): void
    {
        $table = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('id'), new Identifier('lane')]);
        $id = new IntegerComparison(IntegerOperator::Equal, new ColumnReference(0), new ColumnReference(0, 1));
        $lane = new IntegerComparison(IntegerOperator::Equal, new ColumnReference(1, 1), new ColumnReference(1));
        $condition = new LogicalPredicate(LogicalOperator::And, $id, $lane);

        $value = new Join(JoinKind::Full, $table, $table, $condition);

        self::assertSame($condition, $value->on);
    }

    public function testFullRejectsEqualityWithinOneSide(): void
    {
        $table = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('id')]);
        $on = new IntegerComparison(IntegerOperator::Equal, new ColumnReference(0), new ColumnReference(0));
        $this->expectExceptionMessage('requires integer equality between opposite inputs');

        new Join(JoinKind::Full, $table, $table, $on);
    }
}
