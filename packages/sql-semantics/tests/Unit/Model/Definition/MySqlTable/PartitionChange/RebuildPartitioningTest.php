<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\RebuildPartitioning;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RebuildPartitioning::class)]
#[Medium]
final class RebuildPartitioningTest extends TestCase
{
    public function testReadsTheBinlogPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t REORGANIZE PARTITION NO_WRITE_TO_BINLOG');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RebuildPartitioning::class, $alteration);
        self::assertSame(BinlogPolicy::Omit, $alteration->binlog);
    }
}
