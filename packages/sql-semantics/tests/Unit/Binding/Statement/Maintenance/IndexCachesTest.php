<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IndexCache as Cache;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Maintenance\IndexCaches::class)]
#[Medium]
final class IndexCachesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRetainsWholeTableAssignmentsAcrossReleases(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT PRIMARY KEY)', 'CREATE TABLE u(id INT)');
        $binder = new Binder($schema);
        $statement = $binder->bind('CACHE INDEX t KEY (PRIMARY), u INDEX () IN hot');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        self::assertSame($schema->tables[0], $statement->targets[0]->table->declaration);
        self::assertSame(['PRIMARY'], $statement->targets[0]->indexes?->names);
        self::assertSame([], $statement->targets[1]->indexes?->names);
        self::assertInstanceOf(Cache\CacheName::class, $statement->cache);
        self::assertSame('hot', $statement->cache->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testPartitionsRetainsOneTableAndItsSelectedPartitions(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('CACHE INDEX t PARTITION (p0, `ALL`) INDEX () IN DEFAULT');
        self::assertInstanceOf(Statement\CachePartitionIndexesStatement::class, $statement);
        self::assertInstanceOf(Cache\NamedPartitions::class, $statement->partitions);
        self::assertSame(['p0', 'ALL'], $statement->partitions->names);
        self::assertSame([], $statement->target->indexes?->names);
        self::assertSame(Cache\DefaultCache::Instance, $statement->cache);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testIgnoreLeavesBelongsToEachPreloadTarget(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)'));
        $statement = $binder->bind('LOAD INDEX INTO CACHE t, u KEY (ix) IGNORE LEAVES');
        self::assertInstanceOf(Statement\PreloadTableIndexesStatement::class, $statement);
        self::assertFalse($statement->targets[0]->ignoreLeaves);
        self::assertNull($statement->targets[0]->indexes->indexes);
        self::assertTrue($statement->targets[1]->ignoreLeaves);
        self::assertSame(['ix'], $statement->targets[1]->indexes->indexes?->names);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testTargetKeepsPartitionPreloadSeparateFromATableList(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('LOAD INDEX INTO CACHE t PARTITION (ALL) IGNORE LEAVES');
        self::assertInstanceOf(Statement\PreloadPartitionIndexesStatement::class, $statement);
        self::assertSame(Cache\AllPartitions::All, $statement->partitions);
        self::assertTrue($statement->target->ignoreLeaves);
        self::assertSame('t', $statement->target->indexes->table->declaration->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindRejectsMissingTableDeclarations(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CACHE INDEX absent IN DEFAULT');
    }

    public function testBindRetainsUnresolvedTablesWithDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CACHE INDEX absent IN DEFAULT', strict: false);
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        self::assertFalse($statement->targets[0]->table->declaration->resolved);
        self::assertSame(['unknown-table'], array_column($statement->diagnostics, 'reason'));
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsLowercaseCacheRequests')]
    public function testBindReadsLowercaseCacheRequests(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsLowercaseCacheRequests(): iterable
    {
        return [
            'cache index t in default (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, KEY k (a)) PARTITION BY HASH (a) PARTITIONS 2'], 'cache index t in default', 'CACHE INDEX `t` IN DEFAULT'],
            'cache index t partition (all) in c (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, KEY k (a)) PARTITION BY HASH (a) PARTITIONS 2'], 'cache index t partition (all) in c', 'CACHE INDEX `t` PARTITION(ALL) IN `c`'],
            'CACHE INDEX t PARTITION (p0, p1) KEY (k) IN c (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, KEY k (a)) PARTITION BY HASH (a) PARTITIONS 2'], 'CACHE INDEX t PARTITION (p0, p1) KEY (k) IN c', 'CACHE INDEX `t` PARTITION(`p0`, `p1`) INDEX(`k`) IN `c`'],
            'load index into cache t partition (all) ignore leaves (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, KEY k (a)) PARTITION BY HASH (a) PARTITIONS 2'], 'load index into cache t partition (all) ignore leaves', 'LOAD INDEX INTO CACHE `t` PARTITION(ALL) IGNORE LEAVES'],
            'LOAD INDEX INTO CACHE t IGNORE LEAVES, t INDEX (k) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, KEY k (a)) PARTITION BY HASH (a) PARTITIONS 2'], 'LOAD INDEX INTO CACHE t IGNORE LEAVES, t INDEX (k)', 'LOAD INDEX INTO CACHE `t` IGNORE LEAVES, `t` INDEX(`k`)'],
        ];
    }
}
