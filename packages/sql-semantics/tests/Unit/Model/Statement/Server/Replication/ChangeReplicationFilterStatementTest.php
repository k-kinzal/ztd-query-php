<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\DatabaseFilter;
use SqlSemantics\Model\Configuration\Replication\Filter\FilterRule;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeReplicationFilterStatement::class)]
#[Medium]
final class ChangeReplicationFilterStatementTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`a`)'])]
    #[TestWith(['mysql-8.0.44', "CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`a`) FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.4.7', "CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`a`) FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-9.1.0', 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`a`)'])]
    public function testWithOriginPreservesFiltersAndChannel(string $version, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->filters, $copy->filters);
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($copy));
        self::assertSame(StatementKind::Change, $statement->kind);
    }

    public function testWithFiltersReplacesTheRulesImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a)');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_IGNORE_DB = (`b`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withFilters([new DatabaseFilter(FilterRule::IgnoreDatabase, ['b'])])));
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`a`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithChannelReplacesTheChannelImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_DO_DB = ()');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertSame("CHANGE REPLICATION FILTER REPLICATE_DO_DB = () FOR CHANNEL 'x'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withChannel('x')));
        self::assertNull($statement->channel);
    }

    public function testRejectsARepeatedRule(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a), REPLICATE_DO_DB = (b)');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertEquals([new DatabaseFilter(FilterRule::DoDatabase, ['b'])], $statement->filters);
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationFilterStatement($statement->origin, [...$statement->filters, ...$statement->filters]);
    }

    public function testRejectsAChannelBeforeMySql80(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_DO_DB = ()');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationFilterStatement($statement->origin, $statement->filters, 'c');
    }

    public function testRejectsMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('DO 1');
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationFilterStatement($statement->origin, [new DatabaseFilter(FilterRule::DoDatabase, [])]);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_DO_DB = ()');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationFilterStatement(new Origin('s0', $statement->source, Dialect::Sqlite), $statement->filters);
    }
}
