<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundStep;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;

#[CoversClass(CompoundStep::class)]
#[Medium]
final class CompoundStepTest extends TestCase
{
    public function testRenderWritesTheOperatorBeforeTheArm(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select 1 union all select 2 except values (3)');

        self::assertInstanceOf(Compound::class, $query->statement);
        self::assertSame(CompoundOperator::UnionAll, $query->statement->steps[0]->operator);
        self::assertInstanceOf(Select::class, $query->statement->steps[0]->query);
        self::assertSame(CompoundOperator::Except, $query->statement->steps[1]->operator);
        self::assertInstanceOf(ValuesClause::class, $query->statement->steps[1]->query);
        self::assertSame('SELECT 1 UNION ALL SELECT 2 EXCEPT VALUES (3)', $query->toString());
    }

    public function testRenderWritesIntersect(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 INTERSECT SELECT 1');

        self::assertInstanceOf(Compound::class, $query->statement);
        self::assertSame(CompoundOperator::Intersect, $query->statement->steps[0]->operator);
        self::assertSame('SELECT 1 INTERSECT SELECT 1', $query->toString());
    }
}
