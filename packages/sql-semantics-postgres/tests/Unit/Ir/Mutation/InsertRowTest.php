<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertCell;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertRow;

#[CoversClass(InsertRow::class)]
#[Small]
final class InsertRowTest extends TestCase
{
    public function testToStringKeepsTheCellPositions(): void
    {
        $values = [new InsertCell(new IntegerLiteral('-2')), new InsertCell(null)];
        $row = new InsertRow($values);
        array_pop($values);

        self::assertSame('(CAST(-2 AS pg_catalog.int4), NULL)', $row->toString());
    }
}
