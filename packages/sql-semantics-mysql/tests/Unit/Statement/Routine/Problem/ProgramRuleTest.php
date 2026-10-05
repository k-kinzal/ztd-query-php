<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(ProgramRule::class)]
#[Small]
final class ProgramRuleTest extends TestCase
{
    public function testCasesHoldTheirMessagePatterns(): void
    {
        self::assertSame([
            'Duplicate parameter: %s',
            'Duplicate variable: %s',
            'Duplicate condition: %s',
            'Duplicate cursor: %s',
            'Redefining label %s',
            'End-label %s without match',
            'LEAVE with no matching label: %s',
            'ITERATE with no matching label: %s',
            'Undefined CONDITION: %s',
            'Undefined CURSOR: %s',
            'Undeclared variable: %s',
            'RETURN is only allowed in a FUNCTION',
            'No RETURN found in FUNCTION %s',
            'Variable or condition declaration after cursor or handler declaration',
            'Cursor declaration after handler declaration',
            "Bad SQLSTATE: '%s'",
            "Incorrect CONDITION value: '0'",
            'Duplicate handler declared in the same block',
            'SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE',
            "Duplicate condition information item '%s'",
            '%s is not allowed in stored procedures',
            "Can't create a %s from within another stored routine",
            "Can't drop or alter a %s from within another stored routine",
            'Recursion of EVENT DDL statements is forbidden when body is present',
            'Not allowed to return a result set from a %s',
            'Explicit or implicit commit is not allowed in stored function or trigger.',
            '%s is not allowed in stored function or trigger',
            'Updating of OLD row is not allowed in trigger',
            'Updating of NEW row is not allowed in after trigger',
            'There is no NEW row in on DELETE trigger',
        ], array_map(static fn (ProgramRule $rule): string => $rule->value, ProgramRule::cases()));
    }

    public function testCasesNameTheCasesOfTheServerErrors(): void
    {
        self::assertSame([
            'DuplicateParameter',
            'DuplicateVariable',
            'DuplicateCondition',
            'DuplicateCursor',
            'RedefinedLabel',
            'EndLabelMismatch',
            'LeaveWithoutLabel',
            'IterateWithoutLabel',
            'UndefinedCondition',
            'UndefinedCursor',
            'UndeclaredVariable',
            'ReturnOutsideFunction',
            'MissingReturn',
            'DeclarationAfterCursorOrHandler',
            'CursorAfterHandler',
            'BadSqlState',
            'ZeroErrorCode',
            'DuplicateHandler',
            'SignalConditionKind',
            'DuplicateSignalItem',
            'BadStatement',
            'RecursiveCreate',
            'NestedAlterOrDrop',
            'EventRecursion',
            'ResultSet',
            'CommitInFunction',
            'FunctionStatement',
            'OldRowUpdate',
            'AfterRowUpdate',
            'NoNewRow',
        ], array_map(static fn (ProgramRule $rule): string => $rule->name, ProgramRule::cases()));
    }
}
