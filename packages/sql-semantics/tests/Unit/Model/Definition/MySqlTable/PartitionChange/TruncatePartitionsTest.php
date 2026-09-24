<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\TruncatePartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TruncatePartitions::class)]
#[Medium]
final class TruncatePartitionsTest extends TestCase
{
    public function testReadsTheSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t TRUNCATE PARTITION p');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(TruncatePartitions::class, $alteration);
        self::assertInstanceOf(NamedPartitions::class, $alteration->partitions);
        self::assertSame(['p'], $alteration->partitions->names);
    }
}
