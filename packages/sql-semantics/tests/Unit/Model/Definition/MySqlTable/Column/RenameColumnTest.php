<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\RenameColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameColumn::class)]
#[Medium]
final class RenameColumnTest extends TestCase
{
    public function testReadsBothNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t RENAME COLUMN n TO m');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RenameColumn::class, $alteration);
        self::assertSame(['n', 'm'], [$alteration->column, $alteration->newName]);
    }

    public function testRejectsAnEmptyNewName(): void
    {
        $this->expectException(InvalidStructure::class);
        new RenameColumn('n', '');
    }
}
