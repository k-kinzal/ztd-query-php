<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;
use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(QueryClauses::class)]
#[Small]
final class QueryClausesTest extends TestCase
{
    public function testJoinsAndKindPreserveBothDirectionsOfAFullJoin(): void
    {
        $sqlite = new Semantics();
        $table = $sqlite->inspect('CREATE TABLE t (a INT)');
        $query = $sqlite->analyze('SELECT left_t.a, right_t.a FROM t left_t FULL JOIN t right_t ON left_t.a IS right_t.a', [$table]);

        self::assertSame(JoinKind::Full, $query->joins[0]->kind);
        self::assertSame('t', $query->joins[0]->source->table->value);
    }

    public function testKindPreservesAnExplicitCrossJoin(): void
    {
        $sqlite = new Semantics();
        $table = $sqlite->inspect('CREATE TABLE t (a INT)');
        $query = $sqlite->analyze('SELECT x.a FROM t x CROSS JOIN t y', [$table]);

        self::assertSame(JoinKind::Cross, $query->joins[0]->kind);
    }

    public function testPredicateAndOrderingUseTheirResolvedOperands(): void
    {
        $query = (new Semantics())->analyze('SELECT 3 WHERE (3 < 4) AND ?02 ORDER BY 1 DESC NULLS FIRST');

        self::assertInstanceOf(Logical::class, $query->where);
        self::assertSame(1, $query->orderBy[0]->position);
        self::assertTrue($query->orderBy[0]->descending);
        self::assertTrue($query->orderBy[0]->nullsFirst);
    }

    public function testOrderingDefaultsToAscendingNullsFirst(): void
    {
        $query = (new Semantics())->analyze('SELECT NULL, 2 ORDER BY 2');

        self::assertSame(2, $query->orderBy[0]->position);
        self::assertFalse($query->orderBy[0]->descending);
        self::assertTrue($query->orderBy[0]->nullsFirst);
    }
}
