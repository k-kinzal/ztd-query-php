<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\SignalRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SignalRule::class)]
#[Medium]
final class SignalRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatementLowersSignal(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerStatementLowersSignal')]
    public function testStatementLowersSignal(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("signal sqlstate '45000'");

        self::assertEquals(new Signal(new SqlState(new Text('45000'))), $operation->statement);
        self::assertSame("SIGNAL SQLSTATE '45000'", $operation->toString());
    }

    /**
     * @return iterable<string, array{string, string, Resignal, string}>
     */
    public static function providerStatementLowersResignal(): iterable
    {
        yield 'without a condition in 5.6' => ['mysql-5.6.51', 'resignal', new Resignal(null), 'RESIGNAL'];
        yield 'with items only in 5.7' => ['mysql-5.7.44', 'resignal set mysql_errno = 1', new Resignal(null, [new SignalItem(ConditionItemName::MysqlErrno, new NumberLiteral('1'))]), 'RESIGNAL SET MYSQL_ERRNO = 1'];
        yield 'with an sqlstate in 8.0' => ['mysql-8.0.44', "resignal sqlstate '45000'", new Resignal(new SqlState(new Text('45000'))), "RESIGNAL SQLSTATE '45000'"];
        yield 'with an sqlstate and items in 9.1' => ['mysql-9.1.0', "resignal sqlstate '45000' set message_text = 'x'", new Resignal(new SqlState(new Text('45000')), [new SignalItem(ConditionItemName::MessageText, new StringLiteral(['x']))]), "RESIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'x'"];
    }

    #[DataProvider('providerStatementLowersResignal')]
    public function testStatementLowersResignal(string $release, string $sql, Resignal $expected, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertEquals($expected, $operation->statement);
        self::assertSame($rendering, $operation->toString());
    }

    public function testStatementRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new SignalRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: rule: SIGNAL x');

        $rule->statement(new Form(new Node('rule', 0, []), 'rule: SIGNAL x'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerConditionLowersAConditionName(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerConditionLowersAConditionName')]
    public function testConditionLowersAConditionName(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("create procedure p() begin declare c condition for sqlstate '45000'; signal c; resignal c; end");
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertEquals([new Signal(new ConditionName(new Name('c'))), new Resignal(new ConditionName(new Name('c')))], $statement->body->statements);
        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c; RESIGNAL c; END", $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testConditionLowersAnSqlstateWithTheWordValue(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("signal sqlstate value '01000'");

        self::assertEquals(new Signal(new SqlState(new Text('01000'))), $operation->statement);
        self::assertSame("SIGNAL SQLSTATE '01000'", $operation->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerItemsLowersEveryItemNameInOrder(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerItemsLowersEveryItemNameInOrder')]
    public function testItemsLowersEveryItemNameInOrder(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("signal sqlstate '45000' set class_origin = 1, subclass_origin = 1, constraint_catalog = 1, constraint_schema = 1, constraint_name = 1, catalog_name = 1, schema_name = 1, table_name = 1, column_name = 1, cursor_name = 1, message_text = 1, mysql_errno = 1");
        $statement = $operation->statement;
        self::assertInstanceOf(Signal::class, $statement);

        self::assertSame([
            ConditionItemName::ClassOrigin, ConditionItemName::SubclassOrigin, ConditionItemName::ConstraintCatalog, ConditionItemName::ConstraintSchema,
            ConditionItemName::ConstraintName, ConditionItemName::CatalogName, ConditionItemName::SchemaName, ConditionItemName::TableName,
            ConditionItemName::ColumnName, ConditionItemName::CursorName, ConditionItemName::MessageText, ConditionItemName::MysqlErrno,
        ], array_map(static fn (SignalItem $item): ConditionItemName => $item->name, $statement->items));
        self::assertSame("SIGNAL SQLSTATE '45000' SET CLASS_ORIGIN = 1, SUBCLASS_ORIGIN = 1, CONSTRAINT_CATALOG = 1, CONSTRAINT_SCHEMA = 1, CONSTRAINT_NAME = 1, CATALOG_NAME = 1, SCHEMA_NAME = 1, TABLE_NAME = 1, COLUMN_NAME = 1, CURSOR_NAME = 1, MESSAGE_TEXT = 1, MYSQL_ERRNO = 1", $operation->toString());
    }

    public function testItemsLowersAnAbsentClauseAsEmpty(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("signal sqlstate '45000'")->statement;
        self::assertInstanceOf(Signal::class, $statement);

        self::assertSame([], $statement->items);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerOperandLowersLiteralsVariablesAndNames(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerOperandLowersLiteralsVariablesAndNames')]
    public function testOperandLowersLiteralsVariablesAndNames(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("create procedure p(m text) signal sqlstate '45000' set message_text = m, class_origin = @a, subclass_origin = @@session.x, table_name = null, mysql_errno = 1000, column_name = 'c'");
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Signal::class, $statement->body);

        self::assertEquals([
            new SignalItem(ConditionItemName::MessageText, new ColumnUse(new Name('m'))),
            new SignalItem(ConditionItemName::ClassOrigin, new UserVariable(new Name('a'))),
            new SignalItem(ConditionItemName::SubclassOrigin, new SystemVariable(new Name('x'), VariableScope::Session)),
            new SignalItem(ConditionItemName::TableName, new NullLiteral()),
            new SignalItem(ConditionItemName::MysqlErrno, new NumberLiteral('1000')),
            new SignalItem(ConditionItemName::ColumnName, new StringLiteral(['c'])),
        ], $statement->body->items);
        self::assertSame("CREATE PROCEDURE p(m TEXT) SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = m, CLASS_ORIGIN = @a, SUBCLASS_ORIGIN = @@SESSION.x, TABLE_NAME = NULL, MYSQL_ERRNO = 1000, COLUMN_NAME = 'c'", $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}
