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
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(Resignal::class)]
#[Medium]
final class ResignalTest extends TestCase
{
    public function testDeriveStatementAcceptsTheBareStatement(): void
    {
        $resignal = (new Semantics(Dialect::MySql))->analyze('RESIGNAL');
        $statement = $resignal->statement;
        self::assertInstanceOf(Resignal::class, $statement);

        self::assertNull($statement->condition);
        self::assertSame([], $statement->items);
        self::assertSame([], $resignal->facts->diagnostics);
    }

    public function testDeriveStatementAcceptsAnSqlStateValueAndItsItems(): void
    {
        $resignal = (new Semantics(Dialect::MySql))->analyze("RESIGNAL SQLSTATE '01000' SET CLASS_ORIGIN = 'x', SUBCLASS_ORIGIN = 'y'");
        $statement = $resignal->statement;
        self::assertInstanceOf(Resignal::class, $statement);
        self::assertInstanceOf(SqlState::class, $statement->condition);

        self::assertSame('01000', $statement->condition->state->value);
        self::assertSame([ConditionItemName::ClassOrigin, ConditionItemName::SubclassOrigin], array_map(static fn (SignalItem $item): ConditionItemName => $item->name, $statement->items));
        self::assertSame([], $resignal->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, ProgramRule, string}>
     */
    public static function providerDeriveStatementReportsTheBrokenRule(): iterable
    {
        yield 'success value' => ["RESIGNAL SQLSTATE '00000'", ProgramRule::BadSqlState, "Bad SQLSTATE: '00000'"];
        yield 'long value' => ["RESIGNAL SQLSTATE '450001'", ProgramRule::BadSqlState, "Bad SQLSTATE: '450001'"];
        yield 'name without declaration' => ['RESIGNAL gone SET MYSQL_ERRNO = 5', ProgramRule::UndefinedCondition, 'Undefined CONDITION: gone'];
        yield 'item set twice' => ["RESIGNAL SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = 'b'", ProgramRule::DuplicateSignalItem, "Duplicate condition information item 'MESSAGE_TEXT'"];
    }

    #[DataProvider('providerDeriveStatementReportsTheBrokenRule')]
    public function testDeriveStatementReportsTheBrokenRule(string $sql, ProgramRule $rule, string $message): void
    {
        $resignal = (new Semantics(Dialect::MySql))->analyze($sql);
        $problem = $resignal->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $resignal->facts->diagnostics);
        self::assertSame($rule, $problem->rule);
        self::assertSame($message, $problem->message());
    }

    public function testDeriveProgramResolvesADeclaredConditionInAHandler(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p(m TEXT) BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL c SET MESSAGE_TEXT = m; END");
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $handler = $body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        $resignal = $handler->statement;
        self::assertInstanceOf(Resignal::class, $resignal);
        self::assertInstanceOf(ConditionName::class, $resignal->condition);

        self::assertSame('c', $resignal->condition->name->value);
        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsTheBrokenRule(): iterable
    {
        yield 'condition of an error code' => [
            'CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR 1051; RESIGNAL gone; END',
            ['SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE'],
        ];
        yield 'name without declaration' => [
            'CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL gone; END',
            ['Undefined CONDITION: gone'],
        ];
        yield 'success value' => [
            "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL SQLSTATE '00000'; END",
            ["Bad SQLSTATE: '00000'"],
        ];
        yield 'item set twice in a handler' => [
            "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN RESIGNAL SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = 'b'; END; END",
            ["Duplicate condition information item 'MESSAGE_TEXT'"],
        ];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsTheBrokenRule')]
    public function testDeriveProgramReportsTheBrokenRule(string $sql, array $messages): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheStatement(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51', 'resignal', 'RESIGNAL'];
        yield 'mysql 5.7' => ['mysql-5.7.44', 'RESIGNAL SET MYSQL_ERRNO = 5', 'RESIGNAL SET MYSQL_ERRNO = 5'];
        yield 'mysql 8.0' => ['mysql-8.0.44', "resignal sqlstate value '01000' set class_origin = 'x'", "RESIGNAL SQLSTATE '01000' SET CLASS_ORIGIN = 'x'"];
        yield 'mysql 9.1' => ['mysql-9.1.0', "RESIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @m, MYSQL_ERRNO = NULL", "RESIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @m, MYSQL_ERRNO = NULL"];
    }

    #[DataProvider('providerRenderWritesTheStatement')]
    public function testRenderWritesTheStatement(string $release, string $sql, string $expected): void
    {
        $resignal = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertInstanceOf(Resignal::class, $resignal->statement);
        self::assertSame($expected, $resignal->toString());
    }

    public function testRenderWritesAConditionNameInAProgram(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL c SET MESSAGE_TEXT = 'x'; END");

        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL c SET MESSAGE_TEXT = 'x'; END", $create->toString());
    }
}
