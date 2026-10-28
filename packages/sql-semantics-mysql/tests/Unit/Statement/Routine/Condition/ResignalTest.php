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

#[CoversClass(Resignal::class)]
#[Medium]
final class ResignalTest extends TestCase
{
    public function testDeriveStatementAcceptsTheBareStatement(): void
    {
        $resignal = (new Semantics(Dialect::MySql))->analyze('RESIGNAL');
        $statement = $resignal;
        self::assertInstanceOf(Resignal::class, $statement);

        self::assertNull($statement->condition);
        self::assertSame([], $statement->items);
        self::assertSame([], $resignal->diagnostics());
    }

    public function testDeriveStatementAcceptsAnSqlStateValueAndItsItems(): void
    {
        $resignal = (new Semantics(Dialect::MySql))->analyze("RESIGNAL SQLSTATE '01000' SET CLASS_ORIGIN = 'x', SUBCLASS_ORIGIN = 'y'");
        $statement = $resignal;
        self::assertInstanceOf(Resignal::class, $statement);
        self::assertInstanceOf(SqlState::class, $statement->condition);

        self::assertSame('01000', $statement->condition->state->value);
        self::assertSame([ConditionItemName::ClassOrigin, ConditionItemName::SubclassOrigin], array_map(static fn (SignalItem $item): ConditionItemName => $item->name, $statement->items));
        self::assertSame([], $resignal->diagnostics());
    }

    /**
     * @return iterable<string, array{string, ProgramRule, string}>
     */
    public static function providerDeriveStatementReportsTheBrokenRule(): iterable
    {
        yield 'name without declaration' => ['RESIGNAL gone SET MYSQL_ERRNO = 5', ProgramRule::UndefinedCondition, 'Undefined CONDITION: gone'];
    }

    #[DataProvider('providerDeriveStatementReportsTheBrokenRule')]
    public function testDeriveStatementReportsTheBrokenRule(string $sql, ProgramRule $rule, string $message): void
    {
        $resignal = (new Semantics(Dialect::MySql))->analyze($sql);
        $problem = $resignal->diagnostics()[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $resignal->diagnostics());
        self::assertSame($rule, $problem->rule);
        self::assertSame($message, $problem->message());
    }

    public function testDeriveProgramResolvesADeclaredConditionInAHandler(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p(m TEXT) BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL c SET MESSAGE_TEXT = m; END");
        $statement = $create;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $handler = $body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        $resignal = $handler->statement;
        self::assertInstanceOf(Resignal::class, $resignal);
        self::assertInstanceOf(ConditionName::class, $resignal->condition);

        self::assertSame('c', $resignal->condition->name->value);
        self::assertSame([], $create->diagnostics());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsRepeatedItems(): iterable
    {
        yield 'item set twice in a handler' => [
            "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN RESIGNAL SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = 'b'; END; END",
            ["Duplicate condition information item 'MESSAGE_TEXT'"],
        ];
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsTheBrokenRuleBeforePublication(): iterable
    {
        yield 'condition of an error code' => [
            'CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR 1051; RESIGNAL gone; END',
            ['SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE'],
        ];
        yield 'name without declaration' => [
            'CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL gone; END',
            ['Undefined CONDITION: gone'],
        ];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsRepeatedItems')]
    public function testDeriveProgramReportsRepeatedItems(string $sql, array $messages): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertSame($messages, array_map(static fn ($problem): string => $problem->message(), $statement->diagnostics()));
        self::assertSame($sql, $statement->toString());
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsTheBrokenRuleBeforePublication')]
    public function testDeriveProgramReportsTheBrokenRuleBeforePublication(string $sql, array $messages): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertCount(1, $notices);
        self::assertSame($messages, [$notices[0]->message()]);
        self::assertTrue($notices[0]->aborts);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheStatement(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51', 'resignal', 'RESIGNAL'];
        yield 'mysql 5.7' => ['mysql-5.7.44', 'RESIGNAL SET MYSQL_ERRNO = 5', 'RESIGNAL SET MYSQL_ERRNO = 5'];
        yield 'mysql 8.0' => ['mysql-8.0.44', "resignal sqlstate value '01000' set class_origin = 'x'", "RESIGNAL SQLSTATE VALUE '01000' SET CLASS_ORIGIN = 'x'"];
        yield 'mysql 9.1' => ['mysql-9.1.0', "RESIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @m, MYSQL_ERRNO = NULL", "RESIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @m, MYSQL_ERRNO = NULL"];
    }

    #[DataProvider('providerRenderWritesTheStatement')]
    public function testRenderWritesTheStatement(string $release, string $sql, string $expected): void
    {
        $resignal = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertInstanceOf(Resignal::class, $resignal);
        self::assertSame($expected, $resignal->toString());
    }

    public function testRenderWritesAConditionNameInAProgram(): void
    {
        $create = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL c SET MESSAGE_TEXT = 'x'; END");

        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL c SET MESSAGE_TEXT = 'x'; END", $create->toString());
    }

    public function testDeriveStatementRetainsRepeatedItemsAndReportsTheirCondition(): void
    {
        $sql = "RESIGNAL SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = 'b'";
        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(Resignal::class, $statement);
        self::assertCount(2, $statement->items);
        self::assertSame("Duplicate condition information item 'MESSAGE_TEXT'", $statement->diagnostics()[0]->message());
        self::assertSame($sql, $statement->toString());
    }

    public function testRetainsRepeatedItemsWithoutAnOperation(): void
    {
        $item = new SignalItem(ConditionItemName::MysqlErrno, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1234'));
        $statement = new Resignal(null, [$item, new SignalItem($item->name, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('5678'))]);
        self::assertSame([$item->name, $item->name], array_column($statement->items, 'name'));
        self::assertSame($item, $statement->items[0]);
        self::assertNotSame($item, $statement->items[1]);
    }
}
