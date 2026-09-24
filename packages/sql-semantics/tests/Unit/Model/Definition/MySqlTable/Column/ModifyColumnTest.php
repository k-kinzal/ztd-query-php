<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\AfterColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\ModifyColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ModifyColumn::class)]
#[Medium]
final class ModifyColumnTest extends TestCase
{
    public function testReadsTheDeclarationAndPosition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t MODIFY COLUMN n VARCHAR(5) AFTER id');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ModifyColumn::class, $alteration);
        self::assertSame('n', $alteration->definition->name);
        self::assertInstanceOf(AfterColumn::class, $alteration->position);
    }
}
