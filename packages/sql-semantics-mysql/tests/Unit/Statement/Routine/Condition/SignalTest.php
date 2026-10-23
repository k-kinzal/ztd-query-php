<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;

#[CoversClass(Signal::class)]
#[Medium]
final class SignalTest extends TestCase
{
    public function testDeriveStatementAcceptsAnSqlStateValueAndItsItems(): void
    {
        $signal = (new Semantics(Dialect::MySql))->analyze("SIGNAL SQLSTATE VALUE '45000' SET MESSAGE_TEXT = 'no', MYSQL_ERRNO = 1001");
        $statement = $signal->statement;
        self::assertInstanceOf(Signal::class, $statement);
        self::assertInstanceOf(SqlState::class, $statement->condition);

        self::assertSame('45000', $statement->condition->state->value);
        self::assertSame([ConditionItemName::MessageText, ConditionItemName::MysqlErrno], array_map(static fn (SignalItem $item): ConditionItemName => $item->name, $statement->items));
        self::assertSame([], $signal->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, ProgramRule, string}>
     */
    public static function providerDeriveStatementReportsTheBrokenRule(): iterable
    {
        yield 'name without declaration' => ['SIGNAL gone', ProgramRule::UndefinedCondition, 'Undefined CONDITION: gone'];
    }

    #[DataProvider('providerDeriveStatementReportsTheBrokenRule')]
    public function testDeriveStatementReportsTheBrokenRule(string $sql, ProgramRule $rule, string $message): void
    {
        $signal = (new Semantics(Dialect::MySql))->analyze($sql);
        $problem = $signal->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $signal->facts->diagnostics);
        self::assertSame($rule, $problem->rule);
        self::assertSame($message, $problem->message());
    }

    public function testDeriveProgramResolvesADeclaredConditionAndTheVariablesOfTheItems(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p(m TEXT) BEGIN DECLARE v INT; DECLARE gone CONDITION FOR SQLSTATE '45000'; SIGNAL gone SET MESSAGE_TEXT = m, MYSQL_ERRNO = v; END");
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $signal = $body->statements[0];
        self::assertInstanceOf(Signal::class, $signal);
        self::assertInstanceOf(ConditionName::class, $signal->condition);

        self::assertSame('gone', $signal->condition->name->value);
        self::assertCount(2, $signal->items);
        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramRejectsRepeatedItems(): iterable
    {
        yield 'item set twice' => [
            "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c SET MYSQL_ERRNO = 1, MESSAGE_TEXT = 'a', MYSQL_ERRNO = 2; END",
            ["Duplicate condition information item 'MYSQL_ERRNO'"],
        ];
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsTheBrokenRuleBeforePublication(): iterable
    {
        yield 'condition of an error code' => [
            'CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR 1051; SIGNAL gone; END',
            ['SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE'],
        ];
        yield 'inner declaration of an error code hides the outer one' => [
            "CREATE FUNCTION f() RETURNS INT BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN DECLARE c CONDITION FOR 1051; SIGNAL c; END; RETURN 1; END",
            ['SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE'],
        ];
        yield 'declaration in a closed block' => [
            "CREATE PROCEDURE p() BEGIN BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; END; SIGNAL c; END",
            ['Undefined CONDITION: c'],
        ];
        yield 'name without declaration' => [
            'CREATE PROCEDURE p() BEGIN SIGNAL gone; END',
            ['Undefined CONDITION: gone'],
        ];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramRejectsRepeatedItems')]
    public function testDeriveProgramRejectsRepeatedItems(string $sql, array $messages): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage($messages[0]);
        (new Semantics(Dialect::MySql))->analyze($sql);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsTheBrokenRuleBeforePublication')]
    public function testDeriveProgramReportsTheBrokenRuleBeforePublication(string $sql, array $messages): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('A contradictory declaration must not be published.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody::class, $failure->problem);
            self::assertSame($messages, [$failure->problem->message()]);
            self::assertTrue($failure->aborts);
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheStatement(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51', "signal sqlstate value '45000' set message_text = 'no'", "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no'"];
        yield 'mysql 5.7' => ['mysql-5.7.44', "SIGNAL SQLSTATE '01000'", "SIGNAL SQLSTATE '01000'"];
        yield 'mysql 8.0' => ['mysql-8.0.44', "SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 1001, SCHEMA_NAME = @s", "SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 1001, SCHEMA_NAME = @s"];
        yield 'mysql 8.4' => ['mysql-8.4.7', "SIGNAL SQLSTATE '45000' SET CONSTRAINT_CATALOG = 'a', CONSTRAINT_SCHEMA = 'b', CONSTRAINT_NAME = 'c'", "SIGNAL SQLSTATE '45000' SET CONSTRAINT_CATALOG = 'a', CONSTRAINT_SCHEMA = 'b', CONSTRAINT_NAME = 'c'"];
        yield 'mysql 9.1' => ['mysql-9.1.0', "SIGNAL SQLSTATE '45000' SET CATALOG_NAME = 'a', COLUMN_NAME = 'b', CURSOR_NAME = 'c', SUBCLASS_ORIGIN = 'd'", "SIGNAL SQLSTATE '45000' SET CATALOG_NAME = 'a', COLUMN_NAME = 'b', CURSOR_NAME = 'c', SUBCLASS_ORIGIN = 'd'"];
    }

    #[DataProvider('providerRenderWritesTheStatement')]
    public function testRenderWritesTheStatement(string $release, string $sql, string $expected): void
    {
        $signal = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertInstanceOf(Signal::class, $signal->statement);
        self::assertSame($expected, $signal->toString());
    }

    public function testRenderWritesAConditionNameInAProgram(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE VALUE '45000'; SIGNAL c SET MESSAGE_TEXT = 'x'; END");

        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c SET MESSAGE_TEXT = 'x'; END", $create->toString());
    }

    public function testDeriveStatementRejectsRepeatedItemsBeforePublication(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Duplicate condition information item 'MESSAGE_TEXT'");
        (new Semantics(Dialect::MySql))->analyze("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = 'b'");
    }

    public function testRefusesRepeatedItemsWithoutAnOperation(): void
    {
        $item = new SignalItem(ConditionItemName::MysqlErrno, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1234'));
        $this->expectExceptionMessage("Duplicate condition information item 'MYSQL_ERRNO'");
        new Signal(new SqlState(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('45000')), [$item, $item]);
    }
}
