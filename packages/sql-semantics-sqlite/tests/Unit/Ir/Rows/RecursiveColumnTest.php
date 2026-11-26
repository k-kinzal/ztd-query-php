<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveColumn;

#[CoversClass(RecursiveColumn::class)]
#[Small]
final class RecursiveColumnTest extends TestCase
{
    public function testRetainsTheExactOperand(): void
    {
        $column = new ColumnReference(1);
        $output = new RecursiveColumn($column);

        self::assertSame($column, $output->expression);
    }
}
