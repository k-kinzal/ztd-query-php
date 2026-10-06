<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ProgramFacts::class)]
#[Medium]
final class ProgramFactsTest extends TestCase
{
    public function testBodyKeepsExternalCode(): void
    {
        $body = new ExternalBody(new Text('return 1'));

        self::assertSame($body, (new ProgramFacts())->body($body));
    }

    public function testBodyKeepsAProgramStatementAndAnSqlStatement(): void
    {
        $loop = new Loop([new Leave(new Name('l'))], new Name('l'));
        $set = new SetVariables([new UserAssignment(new UserVariable(new Name('a')), new NumberLiteral('1'))]);
        $facts = new ProgramFacts();

        self::assertSame($loop, $facts->body($loop));
        self::assertSame($set, $facts->body($set));
    }

    public function testBodyRefusesANodeThatIsNoStatement(): void
    {
        $this->expectExceptionMessage('A member of a stored program is a program statement or an SQL statement.');

        (new ProgramFacts())->body(new NumberLiteral('1'));
    }

    #[DataProvider('providerRoutineResolvesTheParametersInTheBody')]
    public function testRoutineResolvesTheParametersInTheBody(string $release, string $sql): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql, $release))->analyze($sql)->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRoutineResolvesTheParametersInTheBody(): iterable
    {
        yield 'a procedure in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p(x INT, y INT) SELECT x + y'];
        yield 'a procedure with modes in 5.7' => ['mysql-5.7.44', 'CREATE PROCEDURE p(IN x INT, OUT y INT) SET y = x'];
        yield 'a function in 8.0' => ['mysql-8.0.44', 'CREATE FUNCTION f(x INT) RETURNS INT RETURN x + 1'];
        yield 'a parameter in another letter case in 9.1' => ['mysql-9.1.0', 'CREATE FUNCTION f(x INT) RETURNS INT BEGIN DECLARE v INT DEFAULT X; RETURN v; END'];
        yield 'a procedure without parameters' => ['mysql-9.1.0', 'CREATE PROCEDURE p() SELECT 1'];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerRoutineReportsTheBrokenRule')]
    public function testRoutineReportsTheBrokenRule(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerRoutineReportsTheBrokenRule(): iterable
    {
        yield 'a duplicate parameter of a procedure' => ['CREATE PROCEDURE p(x INT, X INT) SELECT x', ['Duplicate parameter: X']];
        yield 'a duplicate parameter of a function' => ['CREATE FUNCTION f(x INT, x INT) RETURNS INT RETURN x', ['Duplicate parameter: x']];
        yield 'a parameter written three times' => ['CREATE PROCEDURE p(x INT, x INT, X INT) SELECT x', ['Duplicate parameter: x', 'Duplicate parameter: X']];
        yield 'an unknown name in a procedure body' => ['CREATE PROCEDURE p(x INT) SELECT zz', ['Column zz does not exist.']];
        yield 'a function without RETURN' => ['CREATE FUNCTION f() RETURNS INT BEGIN END', ['No RETURN found in FUNCTION f']];
        yield 'a qualified function without RETURN' => ['CREATE FUNCTION db.g() RETURNS INT SET @a = 1', ['No RETURN found in FUNCTION g']];
        yield 'a function whose loop holds no RETURN' => ['CREATE FUNCTION f(x INT) RETURNS INT BEGIN WHILE x > 0 DO SET x = x - 1; END WHILE; END', ['No RETURN found in FUNCTION f']];
        yield 'a function reported after its body' => ['CREATE FUNCTION f(x INT, x INT) RETURNS INT SELECT zz INTO @a', ['Duplicate parameter: x', 'Column zz does not exist.', 'No RETURN found in FUNCTION f']];
        yield 'a RETURN in a procedure' => ['CREATE PROCEDURE p() RETURN 1', ['RETURN is only allowed in a FUNCTION']];
    }

    #[DataProvider('providerRoutineAcceptsAFunctionWithAReturnAnywhere')]
    public function testRoutineAcceptsAFunctionWithAReturnAnywhere(string $sql): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze($sql)->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRoutineAcceptsAFunctionWithAReturnAnywhere(): iterable
    {
        yield 'as the body' => ['CREATE FUNCTION f() RETURNS INT RETURN 1'];
        yield 'in a handler' => ['CREATE FUNCTION f() RETURNS INT BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION RETURN 1; SET @a = 1; END'];
        yield 'in one branch of IF' => ['CREATE FUNCTION f() RETURNS INT BEGIN IF 1 THEN SET @a = 1; ELSE RETURN 1; END IF; END'];
        yield 'in a CASE' => ['CREATE FUNCTION f() RETURNS INT BEGIN CASE 1 WHEN 1 THEN RETURN 1; END CASE; END'];
        yield 'in a REPEAT loop' => ['CREATE FUNCTION f() RETURNS INT BEGIN REPEAT RETURN 1; UNTIL 1 END REPEAT; END'];
        yield 'in a nested block' => ['CREATE FUNCTION f() RETURNS INT BEGIN BEGIN RETURN 1; END; END'];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerRoutineDerivesNothingFromExternalCode')]
    public function testRoutineDerivesNothingFromExternalCode(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze($sql);
        self::assertInstanceOf(CreateFunction::class, $operation->statement);
        self::assertInstanceOf(ExternalBody::class, $operation->statement->body);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerRoutineDerivesNothingFromExternalCode(): iterable
    {
        yield 'a function without RETURN in SQL' => ['CREATE FUNCTION f() RETURNS INT LANGUAGE JAVASCRIPT AS $$ zz $$', []];
        yield 'a duplicate parameter' => ['CREATE FUNCTION f(x INT, x INT) RETURNS INT LANGUAGE JAVASCRIPT AS $$ return x $$', ['Duplicate parameter: x']];
    }

    public function testRoutineReportsAProgramProblem(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS INT BEGIN END');

        self::assertEquals([new ProgramProblem(ProgramRule::MissingReturn, 'f')], $operation->facts->diagnostics);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerTriggerDerivesTheBodyWithTheRowsOfTheTable')]
    public function testTriggerDerivesTheBodyWithTheRowsOfTheTable(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerTriggerDerivesTheBodyWithTheRowsOfTheTable(): iterable
    {
        yield 'both rows of an update trigger' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET @x = OLD.a + NEW.b', []];
        yield 'a query of the trigger table' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SELECT a INTO @x FROM t WHERE b = NEW.b', []];
        yield 'a local variable' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN DECLARE v INT DEFAULT NEW.a; SET NEW.b = v; END', []];
        yield 'a column of the NEW row it lacks' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @x = NEW.zz', ['Column NEW.zz does not exist.']];
        yield 'a column without row' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET @x = a', ['Column a does not exist.']];
        yield 'the restrictions of a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SELECT NEW.a', ['Not allowed to return a result set from a trigger']];
        yield 'the trigger assignments' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET NEW.a = 1', ['Updating of NEW row is not allowed in after trigger']];
    }

    public function testTriggerDerivesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON u FOR EACH ROW SET @x = 1', [$semantics->analyze('CREATE TABLE t (a INT)')]);

        self::assertSame(['Relation u does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerEventDerivesTheScheduleAndTheBody')]
    public function testEventDerivesTheScheduleAndTheBody(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerEventDerivesTheScheduleAndTheBody(): iterable
    {
        yield 'a query of a table' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT a FROM t', []];
        yield 'a schedule at a point in time' => ['CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 HOUR DO SET @a = 1', []];
        yield 'a name in the interval' => ['CREATE EVENT e ON SCHEDULE EVERY zz DAY DO SELECT 1', ['Column zz does not exist.']];
        yield 'a name in the start' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY STARTS zz DO SELECT 1', ['Column zz does not exist.']];
        yield 'a name in the body' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT zz', ['Column zz does not exist.']];
        yield 'the restrictions of an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO BEGIN SELECT 1; COMMIT; USE db; END', ['USE is not allowed in stored procedures']];
        yield 'an alteration without schedule and body' => ['ALTER EVENT e RENAME TO e2', []];
        yield 'an alteration of the schedule' => ['ALTER EVENT e ON SCHEDULE EVERY zz DAY', ['Column zz does not exist.']];
        yield 'an alteration of the body' => ['ALTER EVENT e DO SELECT zz', ['Column zz does not exist.']];
        yield 'an alteration of both' => ['ALTER EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT a FROM t', []];
    }

    public function testTriggerRefusesAView(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON v FOR EACH ROW SET @x = 1', [$table, $view])->facts->diagnostics));
    }
}
