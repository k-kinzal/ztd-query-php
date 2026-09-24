<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Table\OrderRows;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OrderRows::class)]
#[Medium]
final class OrderRowsTest extends TestCase
{
    public function testReadsColumnsAndDirections(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ORDER BY n DESC, id ASC');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(OrderRows::class, $alteration);
        self::assertSame([true, false], array_map(static fn ($ordering): bool => $ordering->descending, $alteration->orderings));
    }

    public function testRejectsANullsPlacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ORDER BY n');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(OrderRows::class, $alteration);
        $key = $alteration->orderings[0]->key;
        $this->expectException(InvalidStructure::class);
        new OrderRows([new Ordering($key, false, true)]);
    }
}
