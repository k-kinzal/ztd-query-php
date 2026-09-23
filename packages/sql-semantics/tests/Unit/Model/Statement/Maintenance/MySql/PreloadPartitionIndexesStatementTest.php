<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IndexCache as Cache;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\PreloadPartitionIndexesStatement::class)]
#[Medium]
final class PreloadPartitionIndexesStatementTest extends TestCase
{
    public function testWithOriginRejectsAnotherSqlDialect(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('LOAD INDEX INTO CACHE t PARTITION (ALL)');
        self::assertInstanceOf(Statement\PreloadPartitionIndexesStatement::class, $statement);
        $foreign = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($foreign->origin);
    }

    public function testResultColumnsDescribesMaintenanceMessagesWithoutExecuting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('LOAD INDEX INTO CACHE t PARTITION (ALL)');
        self::assertInstanceOf(Statement\PreloadPartitionIndexesStatement::class, $statement);
        self::assertSame(['Table', 'Op', 'Msg_type', 'Msg_text'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('varchar', $statement->resultColumns()[3]->expression->type->name);
    }

    public function testWithTargetRebindsTheRequiredPhysicalTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT PRIMARY KEY)'));
        $statement = $binder->bind('LOAD INDEX INTO CACHE t PARTITION (ALL)');
        self::assertInstanceOf(Statement\PreloadPartitionIndexesStatement::class, $statement);
        $other = $binder->bind('CACHE INDEX u IN DEFAULT');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $other);
        $changed = $statement->withTarget(new Cache\PreloadTarget(new Cache\TableIndexes($other->targets[0]->table, new Cache\NamedIndexes(['PRIMARY'])), true));
        self::assertStringContainsString('`u`', $changed->toString());
        self::assertStringNotContainsString('`u`', $statement->toString());
        self::assertSame([], $changed->diagnostics);
    }

    public function testWithPartitionsKeepsAllSeparateFromANamedPartition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('LOAD INDEX INTO CACHE t PARTITION (ALL)');
        self::assertInstanceOf(Statement\PreloadPartitionIndexesStatement::class, $statement);
        $changed = $statement->withPartitions(new Cache\NamedPartitions(['ALL']));
        self::assertSame(Cache\AllPartitions::All, $statement->partitions);
        self::assertInstanceOf(Cache\NamedPartitions::class, $changed->partitions);
        self::assertSame(['ALL'], $changed->partitions->names);
        self::assertStringContainsString('PARTITION(`ALL`)', $changed->toString());
    }

}
