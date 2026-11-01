<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(\SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredClauses::class)]
#[Medium]
final class StoredClausesTest extends TestCase
{
    public function testWhereRetainsAnUpdatePredicateAndItsOrdering(): void
    {
        self::assertSame('CREATE PROCEDURE p() UPDATE t SET a = 1 WHERE ROW(a, 2) ORDER BY 1, (a, 2) DESC', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() UPDATE t SET a=1 WHERE ROW(a,2) ORDER BY 1,(a,2) DESC')->toString());
    }

    public function testTailRetainsLimitBesideADeferredFilter(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT a FROM t WHERE ROW(1, 2) ORDER BY a DESC LIMIT 2', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT a FROM t WHERE ROW(1,2) ORDER BY a DESC LIMIT 2')->toString());
    }

    public function testOrderingKeepsOrdinalsAlongsideDeferredOperands(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT a FROM t ORDER BY 1, (a, 2) DESC LIMIT 0', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT a FROM t ORDER BY 1,(a,2) DESC LIMIT 0')->toString());
    }

    public function testGroupingRetainsTheModernModifier(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT a FROM t GROUP BY ROLLUP ((a, 2))', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('CREATE PROCEDURE p() SELECT a FROM t GROUP BY ROLLUP((a,2))')->toString());
    }

    public function testItemsPreserveTheLegacyGroupingDirection(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT a FROM t GROUP BY (a, 2) DESC, a', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE PROCEDURE p() SELECT a FROM t GROUP BY (a,2) DESC,a')->toString());
    }

    public function testItemSeparatesScalarOrdinalsFromDeferredItems(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT a FROM t ORDER BY 1,(a,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery::class, $create->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $create->body->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\OrderItem::class, $create->body->query->orderBy[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal::class, $create->body->query->orderBy[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem::class, $create->body->query->orderBy[1]);
    }
}
