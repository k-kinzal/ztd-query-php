<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\PublishedTable::class)]
#[Medium]
final class PublishedTableTest extends TestCase
{
    public function testATableRetainsItsColumnsAndFilter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLE t (b, a) WHERE (a > 0)');
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        $table = $statement->objects[0];
        self::assertInstanceOf(Operand\PublishedTable::class, $table);
        self::assertSame(['b', 'a'], $table->columns);
        self::assertNotNull($table->filter);
    }

    public function testColumnsAreDistinct(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLE t');
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        $table = $statement->objects[0];
        self::assertInstanceOf(Operand\PublishedTable::class, $table);
        $this->expectException(InvalidStructure::class);
        new Operand\PublishedTable($table->table, ['a', 'a']);
    }
}
