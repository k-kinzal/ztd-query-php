<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\CoalescePartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CoalescePartitions::class)]
#[Medium]
final class CoalescePartitionsTest extends TestCase
{
    public function testReadsTheCount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t COALESCE PARTITION NO_WRITE_TO_BINLOG 4');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(CoalescePartitions::class, $alteration);
        self::assertSame([4, BinlogPolicy::Omit], [$alteration->count, $alteration->binlog]);
    }

    public function testRejectsZero(): void
    {
        $this->expectException(InvalidStructure::class);
        new CoalescePartitions(0);
    }
}
