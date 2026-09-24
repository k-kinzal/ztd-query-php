<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\AddPartitionCount;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddPartitionCount::class)]
#[Medium]
final class AddPartitionCountTest extends TestCase
{
    public function testReadsTheCount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD PARTITION PARTITIONS 3');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddPartitionCount::class, $alteration);
        self::assertSame(3, $alteration->count);
    }

    public function testRejectsZero(): void
    {
        $this->expectException(InvalidStructure::class);
        new AddPartitionCount(0);
    }
}
