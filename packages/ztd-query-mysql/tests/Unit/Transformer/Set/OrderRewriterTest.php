<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Set\OrderRewriter;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer::class)]
#[CoversClass(OrderRewriter::class)]
final class OrderRewriterTest extends TestCase
{
    public function testRewriteSetOrderBy(): void
    {
        $tables = ['t' => ['columnTypes' => ['colors' => new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::STRING, "SET('red','blue')")]]];
        $rewriter = new OrderRewriter();
        self::assertSame("SELECT * FROM t ORDER BY (IF(FIND_IN_SET('red', `colors`) > 0, 1, 0) + IF(FIND_IN_SET('blue', `colors`) > 0, 2, 0)) DESC LIMIT 2", $rewriter->rewriteSetOrderBy('SELECT * FROM t ORDER BY `colors` DESC LIMIT 2', $tables));
        self::assertSame('SELECT * FROM t', $rewriter->rewriteSetOrderBy('SELECT * FROM t', $tables));
    }

    public function testColumnMaps(): void
    {
        $tables = ['t' => ['columnTypes' => ['colors' => new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::STRING, "SET('red','blue')")]]];
        self::assertSame([['`t`.`colors`' => ['red', 'blue']], ['colors' => ['red', 'blue']]], (new OrderRewriter())->columnMaps('SELECT * FROM t', $tables));
    }

    public function testRewriteItems(): void
    {
        $rewriter = new OrderRewriter();
        self::assertSame("ORDER BY (IF(FIND_IN_SET('red', `colors`) > 0, 1, 0)) DESC, id LIMIT 1", $rewriter->rewriteItems(['ORDER BY `colors` DESC, id LIMIT 1', '`colors` DESC, id', ' LIMIT 1'], [], ['colors' => ['red']]));
        self::assertSame('ORDER BY `colors`', $rewriter->rewriteItems(['ORDER BY `colors`', '`colors`', ''], [], ['colors' => null]));
    }

    public function testRankExpression(): void
    {
        self::assertSame("(IF(FIND_IN_SET('it''s', `colors`) > 0, 1, 0) + IF(FIND_IN_SET('blue', `colors`) > 0, 2, 0))", (new OrderRewriter())->rankExpression(["it's", 'blue'], '`colors`'));
    }

}
