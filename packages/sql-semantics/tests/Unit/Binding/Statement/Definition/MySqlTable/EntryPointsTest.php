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
        self::assertSame('unknown-column', $statement->diagnostics[0]->reason);
    }
}
