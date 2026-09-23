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

#[CoversClass(Statement\CacheTableIndexesStatement::class)]
#[Medium]
final class CacheTableIndexesStatementTest extends TestCase
{
    public function testWithOriginRejectsAnotherSqlDialect(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('CACHE INDEX t IN DEFAULT');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        $foreign = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($foreign->origin);
    }

    public function testResultColumnsDescribesMaintenanceMessagesWithoutExecuting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CACHE INDEX t IN DEFAULT');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        self::assertSame(['Table', 'Op', 'Msg_type', 'Msg_text'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('varchar', $statement->resultColumns()[3]->expression->type->name);
    }

    public function testWithTargetsRebindsTheRequiredPhysicalTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT PRIMARY KEY)'));
        $statement = $binder->bind('CACHE INDEX t IN DEFAULT');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        $other = $binder->bind('CACHE INDEX u IN DEFAULT');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $other);
        $changed = $statement->withTargets([new Cache\TableIndexes($other->targets[0]->table, new Cache\NamedIndexes(['PRIMARY']))]);
        self::assertStringContainsString('`u`', $changed->toString());
        self::assertStringNotContainsString('`u`', $statement->toString());
        self::assertSame([], $changed->diagnostics);
    }

    public function testWithCachePreservesAnIdentifierContainingSqlPunctuation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CACHE INDEX t IN DEFAULT');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        $changed = $statement->withCache(new Cache\CacheName('hot`; SELECT 1'));
        self::assertSame(Cache\DefaultCache::Instance, $statement->cache);
        self::assertInstanceOf(Cache\CacheName::class, $changed->cache);
        self::assertSame('hot`; SELECT 1', $changed->cache->name);
        self::assertStringContainsString('`hot``; SELECT 1`', $changed->toString());
    }

}
