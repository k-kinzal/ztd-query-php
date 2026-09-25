<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\EntryPoints;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\GeneratedColumnExpressionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Table\PartitionSchemeStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EntryPoints::class)]
#[Medium]
final class EntryPointsTest extends TestCase
{
    public function testBindReadsAPartitioningEntry(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('PARTITION BY LINEAR HASH (a) PARTITIONS 2', strict: false);
        self::assertInstanceOf(PartitionSchemeStatement::class, $statement);
        self::assertSame(2, $statement->partitioning->partitionCount);
    }

    public function testBindReadsAGeneratedColumnExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('PARSE_GCOL_EXPR (a + 1)', strict: false);
        self::assertInstanceOf(GeneratedColumnExpressionStatement::class, $statement);
        self::assertSame([], $statement->diagnostics);
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'PARTITION BY RANGE (id) (PARTITION p0 VALUES LESS THAN (10))', 'PARTITION BY RANGE(`id`)(PARTITION `p0` VALUES LESS THAN(10))'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44', 'PARTITION BY LIST (id + 1) (PARTITION p0 VALUES IN (10))', 'PARTITION BY LIST((`id` + 1))(PARTITION `p0` VALUES IN(10))'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44', 'PARSE_GCOL_EXPR (a + 1)', 'PARSE_GCOL_EXPR((`a` + 1))'])]
    public function testBindLeavesColumnsOfAParserEntryUnresolvedInStrictBinding(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
