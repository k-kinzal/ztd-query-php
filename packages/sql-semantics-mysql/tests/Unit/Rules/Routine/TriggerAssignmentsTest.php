<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\TriggerAssignments;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(TriggerAssignments::class)]
#[Medium]
final class TriggerAssignmentsTest extends TestCase
{
    #[DataProvider('providerCheckAcceptsAnAssignmentToTheNewRowOfABeforeTrigger')]
    public function testCheckAcceptsAnAssignmentToTheNewRowOfABeforeTrigger(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')])->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCheckAcceptsAnAssignmentToTheNewRowOfABeforeTrigger(): iterable
    {
        yield 'NEW in a BEFORE INSERT trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.a = 1'];
        yield 'new in a BEFORE UPDATE trigger' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET new.a = OLD.b'];
        yield 'a column in another letter case' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.A = 1'];
        yield 'a quoted NEW' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET `NEW`.a = 1'];
        yield 'several columns with := ' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.a := 1, NEW.b := NEW.a'];
        yield 'NEW beside other variables' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN DECLARE v INT DEFAULT 1; SET @x = 1, v = 2, NEW.a = v; END'];
    }

    #[DataProvider('providerCheckLeavesOtherAssignmentsAlone')]
    public function testCheckLeavesOtherAssignmentsAlone(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')])->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCheckLeavesOtherAssignmentsAlone(): iterable
    {
        yield 'a user variable in an AFTER DELETE trigger' => ['CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW SET @x = OLD.a'];
        yield 'a system variable with a scope keyword' => ["CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET SESSION sql_mode = ''"];
        yield 'a name qualified by something else' => ['CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW SET x.zz = 1'];
        yield 'a system variable written with @@' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET @@x.y = 1'];
        yield 'NEW in a procedure' => ['CREATE PROCEDURE p() SET NEW.zz = 1'];
        yield 'OLD in an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SET OLD.zz = 1'];
        yield 'OLD in a function' => ['CREATE FUNCTION f() RETURNS INT BEGIN SET OLD.zz = 1; RETURN 1; END'];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerCheckReportsAnAssignmentTheTriggerCannotMake')]
    public function testCheckReportsAnAssignmentTheTriggerCannotMake(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerCheckReportsAnAssignmentTheTriggerCannotMake(): iterable
    {
        yield 'OLD in a BEFORE UPDATE trigger' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET OLD.a = 1', ['Updating of OLD row is not allowed in trigger']];
        yield 'old in a BEFORE INSERT trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET old.a = 1', ['Updating of OLD row is not allowed in trigger']];
        yield 'OLD in an AFTER DELETE trigger' => ['CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW SET OLD.zz = 1', ['Updating of OLD row is not allowed in trigger']];
        yield 'NEW in a BEFORE DELETE trigger' => ['CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW SET NEW.a = 1', ['There is no NEW row in on DELETE trigger']];
        yield 'NEW in an AFTER DELETE trigger' => ['CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW SET NEW.a = 1', ['There is no NEW row in on DELETE trigger']];
        yield 'NEW in an AFTER INSERT trigger' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET NEW.a = 1', ['Updating of NEW row is not allowed in after trigger']];
        yield 'new in an AFTER UPDATE trigger' => ['CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW SET new.zz = 1', ['Updating of NEW row is not allowed in after trigger']];
        yield 'a column the NEW row lacks' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.zz = 1', ['Column NEW.zz does not exist.']];
        yield 'a column the new row lacks' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET new.zz = 1', ['Column new.zz does not exist.']];
        yield 'every item in order' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.a = 1, OLD.b = 2, @x = 3, NEW.zz = 4', ['Updating of OLD row is not allowed in trigger', 'Column NEW.zz does not exist.']];
        yield 'an assignment nested in the body' => ['CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW BEGIN IF NEW.a > 0 THEN SET OLD.a = 1; END IF; END', ['Updating of OLD row is not allowed in trigger']];
        yield 'an assignment in a handler' => ['CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET NEW.a = 1; END', ['Updating of NEW row is not allowed in after trigger']];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerCheckReportsTheAssignmentInEveryRelease')]
    public function testCheckReportsTheAssignmentInEveryRelease(string $release, string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerCheckReportsTheAssignmentInEveryRelease(): iterable
    {
        yield 'NEW after an insert in 5.6' => ['mysql-5.6.51', 'CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET NEW.a = 1', ['Updating of NEW row is not allowed in after trigger']];
        yield 'a missing column in 5.7' => ['mysql-5.7.44', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.zz = 1, NEW.a = 2', ['Column NEW.zz does not exist.']];
        yield 'OLD before an update in 8.0' => ['mysql-8.0.44', 'CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET OLD.b = NEW.b', ['Updating of OLD row is not allowed in trigger']];
        yield 'NEW before an insert in 8.4' => ['mysql-8.4.7', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.b = 2', []];
    }

    public function testCheckReportsNothingForATableThatIsNotKnown(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.zz = 1');

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testCheckReportsAProgramProblemOfTheRule(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET NEW.a = 1', [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertEquals([new ProgramProblem(ProgramRule::AfterRowUpdate)], $operation->facts->diagnostics);
    }
}
