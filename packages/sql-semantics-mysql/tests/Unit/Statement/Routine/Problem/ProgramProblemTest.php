<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(ProgramProblem::class)]
#[Small]
final class ProgramProblemTest extends TestCase
{
    #[DataProvider('providerMessageWritesTheSubjectIntoTheServerText')]
    public function testMessageWritesTheSubjectIntoTheServerText(ProgramRule $rule, string $subject, string $expected): void
    {
        $problem = new ProgramProblem($rule, $subject);

        self::assertSame($expected, $problem->message());
        self::assertSame($rule, $problem->rule);
        self::assertSame($subject, $problem->subject);
    }

    /**
     * @return iterable<string, array{ProgramRule, string, string}>
     */
    public static function providerMessageWritesTheSubjectIntoTheServerText(): iterable
    {
        yield 'a duplicate parameter' => [ProgramRule::DuplicateParameter, 'x', 'Duplicate parameter: x'];
        yield 'a duplicate variable' => [ProgramRule::DuplicateVariable, 'v', 'Duplicate variable: v'];
        yield 'a duplicate condition' => [ProgramRule::DuplicateCondition, 'c', 'Duplicate condition: c'];
        yield 'a duplicate cursor' => [ProgramRule::DuplicateCursor, 'cur', 'Duplicate cursor: cur'];
        yield 'a redefined label' => [ProgramRule::RedefinedLabel, 'l', 'Redefining label l'];
        yield 'an end label without match' => [ProgramRule::EndLabelMismatch, 'm', 'End-label m without match'];
        yield 'a LEAVE without label' => [ProgramRule::LeaveWithoutLabel, 'l', 'LEAVE with no matching label: l'];
        yield 'an ITERATE without label' => [ProgramRule::IterateWithoutLabel, 'l', 'ITERATE with no matching label: l'];
        yield 'an undefined condition' => [ProgramRule::UndefinedCondition, 'c', 'Undefined CONDITION: c'];
        yield 'an undefined cursor' => [ProgramRule::UndefinedCursor, 'cur', 'Undefined CURSOR: cur'];
        yield 'an undeclared variable' => [ProgramRule::UndeclaredVariable, 'y', 'Undeclared variable: y'];
        yield 'a missing RETURN' => [ProgramRule::MissingReturn, 'f', 'No RETURN found in FUNCTION f'];
        yield 'a bad SQLSTATE' => [ProgramRule::BadSqlState, '00000', "Bad SQLSTATE: '00000'"];
        yield 'a duplicate signal item' => [ProgramRule::DuplicateSignalItem, 'MESSAGE_TEXT', "Duplicate condition information item 'MESSAGE_TEXT'"];
        yield 'a bad statement' => [ProgramRule::BadStatement, 'LOCK', 'LOCK is not allowed in stored procedures'];
        yield 'a recursive create' => [ProgramRule::RecursiveCreate, 'PROCEDURE', "Can't create a PROCEDURE from within another stored routine"];
        yield 'a nested alter or drop' => [ProgramRule::NestedAlterOrDrop, 'FUNCTION', "Can't drop or alter a FUNCTION from within another stored routine"];
        yield 'a result set' => [ProgramRule::ResultSet, 'trigger', 'Not allowed to return a result set from a trigger'];
        yield 'a function statement' => [ProgramRule::FunctionStatement, 'Dynamic SQL', 'Dynamic SQL is not allowed in stored function or trigger'];
    }

    #[DataProvider('providerMessageWritesTheServerTextOfARuleWithoutSubject')]
    public function testMessageWritesTheServerTextOfARuleWithoutSubject(ProgramRule $rule, string $expected): void
    {
        $problem = new ProgramProblem($rule);

        self::assertSame($expected, $problem->message());
        self::assertNull($problem->subject);
    }

    /**
     * @return iterable<string, array{ProgramRule, string}>
     */
    public static function providerMessageWritesTheServerTextOfARuleWithoutSubject(): iterable
    {
        yield 'a RETURN outside a function' => [ProgramRule::ReturnOutsideFunction, 'RETURN is only allowed in a FUNCTION'];
        yield 'a declaration after a cursor or handler' => [ProgramRule::DeclarationAfterCursorOrHandler, 'Variable or condition declaration after cursor or handler declaration'];
        yield 'an error code 0' => [ProgramRule::ZeroErrorCode, "Incorrect CONDITION value: '0'"];
        yield 'a duplicate handler' => [ProgramRule::DuplicateHandler, 'Duplicate handler declared in the same block'];
        yield 'a cursor after a handler' => [ProgramRule::CursorAfterHandler, 'Cursor declaration after handler declaration'];
        yield 'a signal of a condition without SQLSTATE' => [ProgramRule::SignalConditionKind, 'SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE'];
        yield 'an event recursion' => [ProgramRule::EventRecursion, 'Recursion of EVENT DDL statements is forbidden when body is present'];
        yield 'a commit in a function' => [ProgramRule::CommitInFunction, 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'an update of the OLD row' => [ProgramRule::OldRowUpdate, 'Updating of OLD row is not allowed in trigger'];
        yield 'an update of the NEW row after' => [ProgramRule::AfterRowUpdate, 'Updating of NEW row is not allowed in after trigger'];
        yield 'a NEW row in a DELETE trigger' => [ProgramRule::NoNewRow, 'There is no NEW row in on DELETE trigger'];
    }

    public function testMessageKeepsTheSubjectVerbatim(): void
    {
        self::assertSame('Duplicate parameter: Weird %s `name`', (new ProgramProblem(ProgramRule::DuplicateParameter, 'Weird %s `name`'))->message());
    }

    public function testProblemsWithTheSameRuleAndSubjectAreEqual(): void
    {
        self::assertEquals(new ProgramProblem(ProgramRule::UndefinedCursor, 'c'), new ProgramProblem(ProgramRule::UndefinedCursor, 'c'));
        self::assertNotEquals(new ProgramProblem(ProgramRule::UndefinedCursor, 'c'), new ProgramProblem(ProgramRule::UndefinedCursor, 'd'));
    }

    public function testARuleAboutANameWithoutSubjectIsRejected(): void
    {
        $this->expectExceptionMessage('A problem names a subject exactly when its rule is about a name.');

        new ProgramProblem(ProgramRule::UndefinedCursor);
    }

    public function testARuleWithoutNameWithASubjectIsRejected(): void
    {
        $this->expectExceptionMessage('A problem names a subject exactly when its rule is about a name.');

        new ProgramProblem(ProgramRule::CommitInFunction, 'COMMIT');
    }
}
