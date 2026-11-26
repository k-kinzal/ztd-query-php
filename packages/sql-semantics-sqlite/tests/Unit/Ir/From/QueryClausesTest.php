<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;
use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(QueryClauses::class)]
#[Small]
final class QueryClausesTest extends TestCase
{
    public function testJoinsAndKindPreserveBothDirectionsOfAFullJoin(): void
    {
        $sqlite = new Semantics();
        $table = (new Inspection())->inspect('CREATE TABLE t (a INT)');
        $query = $sqlite->analyze('SELECT left_t.a, right_t.a FROM t left_t FULL JOIN t right_t ON left_t.a IS right_t.a', $sqlite->context(['CREATE TABLE t (a INT)']));

        self::assertInstanceOf(Select::class, $query);
        self::assertSame(JoinKind::Full, $query->joins[0]->kind);
        self::assertInstanceOf(\SqlSemantics\Platform\Sqlite\Ir\TableSource::class, $query->joins[0]->source);
        self::assertSame('t', $query->joins[0]->source->table->value);
    }

    public function testKindPreservesAnExplicitCrossJoin(): void
    {
        $sqlite = new Semantics();
        $table = (new Inspection())->inspect('CREATE TABLE t (a INT)');
        $query = $sqlite->analyze('SELECT x.a FROM t x CROSS JOIN t y', $sqlite->context(['CREATE TABLE t (a INT)']));

        self::assertInstanceOf(Select::class, $query);
        self::assertSame(JoinKind::Cross, $query->joins[0]->kind);
    }

    public function testPredicateAndOrderingUseTheirResolvedOperands(): void
    {
        $query = (new Semantics())->analyze('SELECT 3 WHERE (3 < 4) AND ?02 ORDER BY 1 DESC NULLS FIRST');

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(Logical::class, $query->where);
        self::assertSame(1, $query->orderBy[0]->position);
        self::assertTrue($query->orderBy[0]->descending);
        self::assertTrue($query->orderBy[0]->nullsFirst);
    }

    public function testOrderingDefaultsToAscendingNullsFirst(): void
    {
        $query = (new Semantics())->analyze('SELECT NULL, 2 ORDER BY 2');

        self::assertInstanceOf(Select::class, $query);
        self::assertSame(2, $query->orderBy[0]->position);
        self::assertFalse($query->orderBy[0]->descending);
        self::assertTrue($query->orderBy[0]->nullsFirst);
    }

    public function testLimitResolvesTheOffsetFirstFormAndSignedInteger(): void
    {
        $select = (new Semantics())->analyze('SELECT DISTINCT 1 LIMIT ?02, +5');

        self::assertInstanceOf(Select::class, $select);
        self::assertTrue($select->distinct);
        self::assertInstanceOf(Limit::class, $select->limit);
        self::assertTrue($select->limit->offsetFirst);
        self::assertInstanceOf(IntegerLiteral::class, $select->limit->count);
        self::assertSame('5', $select->limit->count->decimal);
        self::assertInstanceOf(Parameter::class, $select->limit->offset);
        self::assertSame('02', $select->limit->offset->label);
    }
}
