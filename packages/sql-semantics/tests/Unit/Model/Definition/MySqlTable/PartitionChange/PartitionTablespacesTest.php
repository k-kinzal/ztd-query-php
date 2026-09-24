<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\PartitionTablespaces;
use SqlSemantics\Model\Definition\MySqlTable\Table\TablespaceAction;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionTablespaces::class)]
#[Medium]
final class PartitionTablespacesTest extends TestCase
{
    public function testReadsTheActionAndSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t DISCARD PARTITION p TABLESPACE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(PartitionTablespaces::class, $alteration);
        self::assertSame(TablespaceAction::Discard, $alteration->action);
        self::assertInstanceOf(NamedPartitions::class, $alteration->partitions);
    }
}
