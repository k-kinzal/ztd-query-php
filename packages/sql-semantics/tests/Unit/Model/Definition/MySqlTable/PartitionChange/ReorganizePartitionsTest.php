<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\ReorganizePartitions;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReorganizePartitions::class)]
#[Medium]
final class ReorganizePartitionsTest extends TestCase
{
    public function testReadsTheReplacedAndReplacementPartitions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t REORGANIZE PARTITION a, b INTO (PARTITION c VALUES LESS THAN MAXVALUE)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ReorganizePartitions::class, $alteration);
        self::assertSame(['a', 'b'], $alteration->partitions);
        self::assertSame('c', $alteration->into[0]->name);
    }

    public function testRejectsARepeatedReplacementName(): void
    {
        $this->expectException(InvalidStructure::class);
        new ReorganizePartitions(['a'], [new PartitionDefinition('b'), new PartitionDefinition('B')]);
    }
}
