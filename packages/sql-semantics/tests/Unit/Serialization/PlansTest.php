<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Plan\PostgreSqlFormat;
use SqlSemantics\Model\Plan\PostgreSqlPlan;
use SqlSemantics\Model\Plan\SerializationCost;
use SqlSemantics\Model\Statement\Plan\ExplainConnectionStatement;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Plans;

#[CoversClass(Plans::class)]
#[Medium]
final class PlansTest extends TestCase
{
    #[TestWith([Dialect::Sqlite, null, 'EXPLAIN QUERY PLAN SELECT 1', 'EXPLAIN QUERY PLAN SELECT 1'])]
    #[TestWith([Dialect::Sqlite, null, 'EXPLAIN SELECT 1', 'EXPLAIN SELECT 1'])]
    #[TestWith([Dialect::MySql, null, 'EXPLAIN ANALYZE FORMAT = TREE SELECT 1', 'EXPLAIN ANALYZE FORMAT = TREE SELECT 1'])]
    #[TestWith([Dialect::MySql, 'mysql-5.6.51', 'EXPLAIN EXTENDED SELECT 1', 'EXPLAIN EXTENDED SELECT 1'])]
    #[TestWith([Dialect::MySql, 'mysql-5.6.51', 'EXPLAIN PARTITIONS SELECT 1', 'EXPLAIN PARTITIONS SELECT 1'])]
    #[TestWith([Dialect::PostgreSql, null, 'EXPLAIN (ANALYZE, VERBOSE FALSE, COSTS, SETTINGS, BUFFERS, WAL, TIMING, SUMMARY, MEMORY, SERIALIZE TEXT, FORMAT JSON) SELECT 1', 'EXPLAIN(ANALYZE TRUE, VERBOSE FALSE, COSTS TRUE, SETTINGS TRUE, GENERIC_PLAN FALSE, BUFFERS TRUE, WAL TRUE, TIMING TRUE, SUMMARY TRUE, MEMORY TRUE, SERIALIZE TEXT, FORMAT JSON) SELECT 1'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'EXPLAIN FORMAT=JSON INTO @plan SELECT 1', 'EXPLAIN FORMAT = JSON INTO @`plan` SELECT 1'])]
    #[TestWith([Dialect::PostgreSql, null, 'EXPLAIN SELECT 1', 'EXPLAIN(ANALYZE FALSE, VERBOSE FALSE, COSTS TRUE, SETTINGS FALSE, GENERIC_PLAN FALSE, BUFFERS FALSE, WAL FALSE, MEMORY FALSE, SERIALIZE NONE, FORMAT TEXT) SELECT 1'])]
    public function testWriteSerializesEachDialectsPlanOptions(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertSame($expected, Plans::write($statement)->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(ExplainStatement::class, $rebound);
        self::assertSame($statement->options::class, $rebound->options::class);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteKeepsTheConnectionIdentifierSpelling(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('EXPLAIN FORMAT = JSON FOR CONNECTION 42');
        self::assertInstanceOf(ExplainConnectionStatement::class, $statement);
        self::assertSame('EXPLAIN FORMAT = JSON FOR CONNECTION 42', $statement->toString());
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(ExplainConnectionStatement::class, $rebound);
        self::assertSame('42', $rebound->connection->spelling);
        self::assertSame(MySqlFormat::Json, $rebound->format);
    }

    public function testFormatWritesNothingForTheDefaultFormat(): void
    {
        self::assertSame([], Plans::format(MySqlFormat::Default));
        self::assertSame(['FORMAT', '=', 'TREE'], array_map(static fn ($part): string => $part->toString(), Plans::format(MySqlFormat::Tree)));
    }

    public function testPostgresWritesOnlyDecidedFlagsAndAlwaysTheFormat(): void
    {
        $options = new PostgreSqlPlan(analyze: true, timing: false, serialization: SerializationCost::Binary, format: PostgreSqlFormat::Yaml);
        self::assertSame('EXPLAIN(ANALYZE TRUE, VERBOSE FALSE, COSTS TRUE, SETTINGS FALSE, GENERIC_PLAN FALSE, BUFFERS FALSE, WAL FALSE, TIMING FALSE, MEMORY FALSE, SERIALIZE BINARY, FORMAT YAML)', Plans::postgres($options)->toString());
    }

    public function testMysqlWritesTheDatabaseScopeAfterTheOptions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
        $statement = $binder->bind('EXPLAIN FORMAT=TREE FOR SCHEMA sales SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Plan\ExplainInDatabaseStatement::class, $statement);
        self::assertSame('EXPLAIN FORMAT = TREE', Plans::mysql($statement->options)->toString());
        self::assertSame('EXPLAIN FORMAT = TREE FOR DATABASE `sales` SELECT 1', Plans::write($statement)->toString());
    }
}
