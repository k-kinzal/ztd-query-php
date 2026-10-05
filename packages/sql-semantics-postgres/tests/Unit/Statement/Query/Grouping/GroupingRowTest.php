<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingRow::class)]
#[Medium]
final class GroupingRowTest extends TestCase
{
    public function testRenderWritesTheMembersInParentheses(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS a, 2 AS b GROUP BY ((a, 1)), CUBE ((b, (a, 2)))', []);
        self::assertSame('SELECT 1 AS a, 2 AS b GROUP BY ((a, 1)), CUBE ((b, (a, 2)))', $query->toString());
    }

    public function testMembersKeepTheWrittenOrder(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS a GROUP BY (a, 1)', []);
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $row = $select->groupBy[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingRow::class, $row);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference::class, $row->members[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $row->members[1]);
    }
}
