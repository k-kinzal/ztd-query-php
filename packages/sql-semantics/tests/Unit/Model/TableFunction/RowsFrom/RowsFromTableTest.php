<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromFunction;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromTable;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowsFromTable::class)]
#[Medium]
final class RowsFromTableTest extends TestCase
{
    public function testKeepsFunctionsAndOrdinality(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(), g()) WITH ORDINALITY');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation::class, $statement->from);
        self::assertCount(2, $statement->from->table->functions);
        self::assertTrue($statement->from->table->ordinality);
    }

    public function testRejectsAnInvocationOfAnotherDialect(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(ResultStatement::class, $select);
        $this->expectException(InvalidStructure::class);
        new RowsFromTable([new RowsFromFunction($select->resultColumns()[0]->expression)]);
    }
}
