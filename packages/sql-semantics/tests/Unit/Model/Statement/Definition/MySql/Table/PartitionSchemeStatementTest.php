<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\PartitionSchemeStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionSchemeStatement::class)]
#[Medium]
final class PartitionSchemeStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testPartitioningBindsOnMySql5(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('PARTITION BY RANGE COLUMNS (a) (PARTITION p VALUES LESS THAN (1))', strict: false);
        self::assertInstanceOf(PartitionSchemeStatement::class, $statement);
        self::assertSame(StatementKind::Partition, $statement->kind);
        self::assertSame('PARTITION BY RANGE COLUMNS(`a`)(PARTITION `p` VALUES LESS THAN(1))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)));
    }

    public function testWithPartitioningReplacesTheClause(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build());
        $statement = $binder->bind('PARTITION BY KEY (a)', strict: false);
        $other = $binder->bind('PARTITION BY KEY (b) PARTITIONS 2', strict: false);
        self::assertInstanceOf(PartitionSchemeStatement::class, $statement);
        self::assertInstanceOf(PartitionSchemeStatement::class, $other);
        self::assertSame('PARTITION BY KEY(`b`) PARTITIONS 2', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withPartitioning($other->partitioning)));
        self::assertSame($statement->partitioning, $statement->withOrigin($statement->origin)->partitioning);
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('PARTITION BY KEY (a)', strict: false);
        self::assertInstanceOf(PartitionSchemeStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }
}
