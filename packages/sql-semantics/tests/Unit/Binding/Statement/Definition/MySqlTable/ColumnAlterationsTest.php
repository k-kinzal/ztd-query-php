<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\ColumnAlterations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\MySqlTable\Column\AddColumns;
use SqlSemantics\Model\Definition\MySqlTable\Column\ChangeColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\DropColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\FirstColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\ModifyColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\SetColumnVisibility;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Constraint\Check;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnAlterations::class)]
#[Medium]
final class ColumnAlterationsTest extends TestCase
{
    public function testAddReadsAParenthesizedList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD COLUMN (a INT, CHECK (a > 0))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddColumns::class, $alteration);
        self::assertInstanceOf(Check::class, $alteration->constraints[0]);
    }

    public function testRedeclareReadsChangeAndModify(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t CHANGE COLUMN n m INT, MODIFY id BIGINT');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertInstanceOf(ChangeColumn::class, $statement->alterations[0]);
        self::assertInstanceOf(ModifyColumn::class, $statement->alterations[1]);
    }

    public function testDropReadsTheBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t DROP COLUMN n CASCADE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(DropColumn::class, $alteration);
        self::assertSame(DropBehavior::Cascade, $alteration->behavior);
    }

    public function testAlterReadsVisibility(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER COLUMN n SET INVISIBLE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SetColumnVisibility::class, $alteration);
        self::assertFalse($alteration->visible);
    }

    public function testRenameDiagnosesAnEmptyName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TableAlteration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t RENAME COLUMN n TO ``');
    }

    public function testPositionReadsFirst(): void
    {
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t MODIFY n INT FIRST')->find('alter_list_item')[0];
        self::assertSame(FirstColumn::First, ColumnAlterations::position($item, new Scope(new Identifiers(Dialect::MySql))));
    }

    public function testNameReadsTheFirstColumnName(): void
    {
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t CHANGE n m INT')->find('alter_list_item')[0];
        self::assertSame('n', ColumnAlterations::name($item, new Scope(new Identifiers(Dialect::MySql))));
    }

    public function testNamesReadsQualifiedNamesByTheirLastPart(): void
    {
        $item = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('ALTER TABLE t CHANGE t.n m INT')->find('alter_list_item')[0];
        self::assertSame(['n'], ColumnAlterations::names($item, new Scope(new Identifiers(Dialect::MySql))));
    }
}
