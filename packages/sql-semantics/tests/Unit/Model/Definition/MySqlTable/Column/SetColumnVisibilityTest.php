<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\SetColumnVisibility;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetColumnVisibility::class)]
#[Medium]
final class SetColumnVisibilityTest extends TestCase
{
    public function testReadsTheVisibility(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER n SET VISIBLE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SetColumnVisibility::class, $alteration);
        self::assertSame('n', $alteration->column);
        self::assertTrue($alteration->visible);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new SetColumnVisibility('', false);
    }
}
