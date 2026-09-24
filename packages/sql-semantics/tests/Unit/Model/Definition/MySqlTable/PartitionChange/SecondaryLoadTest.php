<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\SecondaryLoad;
use SqlSemantics\Model\Definition\MySqlTable\Table\SecondaryAction;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SecondaryLoad::class)]
#[Medium]
final class SecondaryLoadTest extends TestCase
{
    public function testReadsThePartitions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t SECONDARY_UNLOAD PARTITION (a, b)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SecondaryLoad::class, $alteration);
        self::assertSame([SecondaryAction::Unload, ['a', 'b']], [$alteration->action, $alteration->partitions]);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new SecondaryLoad(SecondaryAction::Load, ['']);
    }
}
