<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\Limit;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\Order;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultSql;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;

#[CoversClass(ResultSql::class)]
#[Small]
final class ResultSqlTest extends TestCase
{
    public function testRenderOrdersTheClausesAndSkipsTheRequestedRows(): void
    {
        $column = new ResultColumn(new IntegerLiteral('1'), new Identifier('n'));
        $result = new Select([$column], distinct: true, orderBy: [new Order(1, true)], limit: new Limit(4, 2));

        self::assertSame(' ORDER BY 1 DESC NULLS FIRST LIMIT 4 OFFSET 2', (new ResultSql())->render($result, new SelectSql()));
    }
}
