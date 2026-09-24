<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\PartitionTablespaces;
use SqlSemantics\Model\Definition\MySqlTable\Table\TablespaceAction;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TablespaceAction::class)]
#[Medium]
final class TablespaceActionTest extends TestCase
{
    public function testIsReadFromPartitionTablespaceCommands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t IMPORT PARTITION ALL TABLESPACE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(PartitionTablespaces::class, $alteration);
        self::assertSame(TablespaceAction::Import, $alteration->action);
    }
}
