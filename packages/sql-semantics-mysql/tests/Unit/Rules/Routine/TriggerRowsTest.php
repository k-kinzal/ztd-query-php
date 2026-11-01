<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\TriggerRows;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TriggerRows::class)]
#[Medium]
final class TriggerRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRefusedAssignments(): iterable
    {
        yield 'OLD in a BEFORE UPDATE trigger' => ['mysql-8.4.7', 'CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET OLD.a = 1', 'Updating of OLD row is not allowed in trigger'];
        yield 'old in a BEFORE INSERT trigger' => ['mysql-8.4.7', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET old.a = 1', 'Updating of OLD row is not allowed in trigger'];
        yield 'NEW in a BEFORE DELETE trigger' => ['mysql-8.4.7', 'CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW SET NEW.a = 1', 'There is no NEW row in on DELETE trigger'];
        yield 'new in an AFTER UPDATE trigger' => ['mysql-8.0.44', 'CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW SET new.zz = 1', 'Updating of NEW row is not allowed in after trigger'];
        yield 'NEW after an insert in 5.6' => ['mysql-5.6.51', 'CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET NEW.a = 1', 'Updating of NEW row is not allowed in after trigger'];
        yield 'the first item in order' => ['mysql-8.4.7', 'CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.a = 1, OLD.b = 2, @x = 3', 'Updating of OLD row is not allowed in trigger'];
        yield 'an assignment nested in the body' => ['mysql-8.4.7', 'CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW BEGIN IF NEW.a > 0 THEN SET OLD.a = 1; END IF; END', 'Updating of OLD row is not allowed in trigger'];
        yield 'an assignment in a handler' => ['mysql-8.4.7', 'CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET NEW.a = 1; END', 'Updating of NEW row is not allowed in after trigger'];
        yield 'before a later syntax error' => ['mysql-8.4.7', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN SET OLD.a = 1; SET @b = 2; END', 'Updating of OLD row is not allowed in trigger'];
    }

    #[DataProvider('providerRefusedAssignments')]
    public function testProblemIsRaisedByTheReadingBeforeTheTriggerIsConstructed(string $release, string $sql, string $message): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze($sql);
            self::fail('The trigger assigns to a row it cannot change.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(M\Routine\Problem\RefusedProgramBody::class, $failure->problem);
            self::assertSame($message, $failure->problem->message());
            self::assertTrue($failure->aborts);
        }
    }

    public function testProblemNamesTheSetStatementOfTheFirstRefusedAssignment(): void
    {
        $set = new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('a'), new M\Literal\NumberLiteral('1'), qualifier: new Name('OLD'))]);
        $body = new M\Routine\Program\Block([], [new M\Utility\Set\SetVariables([new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('x')), new M\Literal\NumberLiteral('1'))]), $set]);
        $problem = (new TriggerRows())->problem($body, TriggerTime::Before, TriggerEvent::Update);
        self::assertNotNull($problem);
        self::assertSame(ProgramRule::OldRowUpdate, $problem[0]->rule);
        self::assertSame($set, $problem[1]);
        self::assertNull((new TriggerRows())->problem($body, TriggerTime::Before, TriggerEvent::Update) === null ? null : (new TriggerRows())->problem(new M\Routine\Program\Block(), TriggerTime::Before, TriggerEvent::Update));
    }

    /**
     * @return iterable<string, array{TriggerTime, TriggerEvent, string, ProgramRule|null}>
     */
    public static function providerAssignments(): iterable
    {
        yield 'NEW before an insert' => [TriggerTime::Before, TriggerEvent::Insert, 'NEW', null];
        yield 'NEW before an update' => [TriggerTime::Before, TriggerEvent::Update, 'new', null];
        yield 'NEW after an update' => [TriggerTime::After, TriggerEvent::Update, 'NEW', ProgramRule::AfterRowUpdate];
        yield 'NEW before a delete' => [TriggerTime::Before, TriggerEvent::Delete, 'NEW', ProgramRule::NoNewRow];
        yield 'OLD after a delete' => [TriggerTime::After, TriggerEvent::Delete, 'OLD', ProgramRule::OldRowUpdate];
        yield 'another qualifier' => [TriggerTime::Before, TriggerEvent::Insert, 'x', null];
    }

    #[DataProvider('providerAssignments')]
    public function testAssignmentDependsOnTheRowWordTheTimeAndTheEvent(TriggerTime $time, TriggerEvent $event, string $qualifier, ?ProgramRule $rule): void
    {
        $item = new M\Utility\Set\NameAssignment(new Name('a'), new M\Literal\NumberLiteral('1'), qualifier: new Name($qualifier));
        self::assertSame($rule, (new TriggerRows())->assignment($item, $time, $event)?->rule);
        self::assertNull((new TriggerRows())->assignment(new M\Utility\Set\NameAssignment(new Name('a'), new M\Literal\NumberLiteral('1'), M\Variable\VariableScope::Session, new Name($qualifier)), $time, $event));
    }
}
