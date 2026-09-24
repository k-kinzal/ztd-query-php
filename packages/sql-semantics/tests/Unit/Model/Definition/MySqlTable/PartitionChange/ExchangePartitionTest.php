<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\ExchangePartition;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExchangePartition::class)]
#[Medium]
final class ExchangePartitionTest extends TestCase
{
    public function testResolvesTheExchangedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)')))->bind('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ExchangePartition::class, $alteration);
        self::assertSame('p', $alteration->partition);
        self::assertTrue($alteration->table->declaration->resolved);
    }

    public function testRejectsAnEmptyPartitionName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)')))->bind('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ExchangePartition::class, $alteration);
        $this->expectException(InvalidStructure::class);
        new ExchangePartition('', $alteration->table);
    }
}
