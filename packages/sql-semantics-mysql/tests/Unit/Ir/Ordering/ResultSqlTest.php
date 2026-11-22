<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\Limit;
use SqlSemantics\Platform\MySql\Ir\Ordering\Order;
use SqlSemantics\Platform\MySql\Ir\Ordering\ResultSql;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(ResultSql::class)]
#[Small]
final class ResultSqlTest extends TestCase
{
    public function testRenderOrdersTheClausesAndSkipsTheRequestedRows(): void
    {
        $column = new ResultColumn(new IntegerLiteral('1'), new Identifier('n'));
        $result = new Select([$column], distinct: true, orderBy: [new Order(1, true)], limit: new Limit(4, 2));

        self::assertSame(' ORDER BY 1 DESC LIMIT 4 OFFSET 2', (new ResultSql())->render($result));
    }
}
