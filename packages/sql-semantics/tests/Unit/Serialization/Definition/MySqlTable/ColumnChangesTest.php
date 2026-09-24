<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\AfterColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\ColumnDefaultAssignment;
use SqlSemantics\Model\Definition\MySqlTable\Column\ModifyColumn;
use SqlSemantics\Model\Definition\MySqlTable\Key\AddIndex;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlTable\ColumnChanges;

#[CoversClass(ColumnChanges::class)]
#[Medium]
final class ColumnChangesTest extends TestCase
{
    public function testWriteWritesColumnAndKeyAlterations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD COLUMN c INT UNIQUE FIRST, ALTER INDEX ix VISIBLE, DROP CONSTRAINT k');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame(['ADD COLUMN `c` integer UNIQUE FIRST', 'ALTER INDEX `ix` VISIBLE', 'DROP CONSTRAINT `k`'], array_map(static fn ($alteration): string => ColumnChanges::write($alteration)?->toString() ?? '', $statement->alterations));
    }

    public function testWriteReturnsNullForTableAlterations(): void
    {
        self::assertNull(ColumnChanges::write(TableCommand::Force));
    }

    public function testDeclarationWritesLocalConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t MODIFY n INT PRIMARY KEY');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ModifyColumn::class, $alteration);
        self::assertSame('`n` integer PRIMARY KEY', ColumnChanges::declaration($alteration->definition, $alteration->constraints)->toString());
    }

    public function testPositionWritesAfter(): void
    {
        self::assertSame('AFTER `id`', ColumnChanges::position(new AfterColumn('id'))->toString());
        self::assertSame('', ColumnChanges::position(null)->toString());
    }

    public function testDefaultWritesSignedLiteralsWithoutParentheses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER n SET DEFAULT -5');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ColumnDefaultAssignment::class, $alteration);
        self::assertSame('- 5', ColumnChanges::default($alteration->default)->toString());
    }

    public function testIndexWritesTheTypeAfterTheKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD INDEX ix USING HASH (n)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddIndex::class, $alteration);
        self::assertSame('INDEX `ix`(`n`) USING HASH', ColumnChanges::index($alteration->index)->toString());
    }

    public function testNameQuotesIdentifiers(): void
    {
        self::assertSame('`a``b`', ColumnChanges::name('a`b')->toString());
    }
}
