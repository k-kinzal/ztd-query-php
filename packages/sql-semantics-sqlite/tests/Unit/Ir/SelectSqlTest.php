<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(SelectSql::class)]
#[Small]
final class SelectSqlTest extends TestCase
{
    public function testRenderAddsOutputAliases(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('"'))]);
        self::assertSame('SELECT 2 AS """"', (new SelectSql())->render($select));
    }

    public function testOrderingRetainsPositionDirectionAndNullPlacement(): void
    {
        $renderer = new SelectSql();

        self::assertSame(' ORDER BY 2 DESC NULLS FIRST', $renderer->ordering([new Order(2, true, true)]));
    }

    public function testLimitPreservesTheFirstSpellingOfASharedSlot(): void
    {
        $limit = new Limit(new Parameter('1'), new Parameter('01'), true);

        self::assertSame('LIMIT ?01, ?1', (new SelectSql())->limit($limit));
    }

    public function testExpressionKeepsNegativeValues(): void
    {
        self::assertSame('-2', (new SelectSql())->expression(new IntegerLiteral('-2'), null));
    }

    public function testSourceQualifiesTheExternalNameAndItsAlias(): void
    {
        $source = new TableSource(new Identifier('main'), new Identifier('metrics'), []);

        self::assertSame('"main"."metrics" AS "__ztd_source_2"', (new SelectSql())->source($source, 2));
    }

    public function testAliasDistinguishesTheFirstAndLaterOccurrences(): void
    {
        $sql = new SelectSql();
        self::assertSame('"__ztd_source"', $sql->alias(0));
        self::assertSame('"__ztd_source_1"', $sql->alias(1));
    }
}
