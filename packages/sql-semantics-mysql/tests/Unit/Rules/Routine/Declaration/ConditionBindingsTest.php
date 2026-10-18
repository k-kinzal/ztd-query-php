<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\ConditionBindings;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ConditionBindings::class)]
#[Small]
final class ConditionBindingsTest extends TestCase
{
    public function testInstructionResolvesDiagnosticTargetsAgainstParametersCaseInsensitively(): void
    {
        $item = new M\Routine\Condition\Diagnostics\InformationItem(new Name('X'), M\Routine\Condition\Diagnostics\StatementItemName::Number);
        $get = new M\Routine\Condition\Diagnostics\GetDiagnostics(new M\Routine\Condition\Diagnostics\StatementDiagnostics([$item]));
        $rules = new ConditionBindings();
        self::assertNull($rules->instruction($get, [new Name('x')], []));
        $problem = $rules->instruction($get, [], []);
        self::assertSame(M\Routine\Problem\ProgramRule::UndeclaredVariable, $problem[0]->rule ?? null);
        self::assertSame($item, $problem[1] ?? null);
    }

    public function testFindKeepsAnInnerNumericConditionFromUsingTheOuterSqlState(): void
    {
        $signal = new M\Routine\Condition\Signal(new M\Routine\Condition\ConditionName(new Name('c')));
        $outer = new M\Routine\Program\ConditionDeclaration(new Name('c'), new M\Routine\Condition\SqlState(new M\Literal\Text('45000')));
        $inner = new M\Routine\Program\ConditionDeclaration(new Name('C'), new M\Routine\Condition\ErrorCode(new M\Literal\Numeral('1000')));
        $nested = new M\Routine\Program\Block([$inner], [$signal]);
        $rules = new ConditionBindings();
        self::assertNull($rules->find(new M\Routine\Program\Block([$outer], [$signal])));
        self::assertSame(M\Routine\Problem\ProgramRule::SignalConditionKind, $rules->find(new M\Routine\Program\Block([$outer], [$nested]))[0]->rule ?? null);
    }

    public function testFindDoesNotLetAnInnerDeclarationEscapeItsBlock(): void
    {
        $declaration = new M\Routine\Program\ConditionDeclaration(new Name('c'), new M\Routine\Condition\SqlState(new M\Literal\Text('45000')));
        $signal = new M\Routine\Condition\Signal(new M\Routine\Condition\ConditionName(new Name('c')));
        $body = new M\Routine\Program\Block([], [new M\Routine\Program\Block([$declaration]), $signal]);
        self::assertSame(M\Routine\Problem\ProgramRule::UndefinedCondition, (new ConditionBindings())->find($body)[0]->rule ?? null);
    }

    public function testFindRejectsTheUnboundInstructionEvenInAnInactiveBranchBeforeOperationConstruction(): void
    {
        $item = new M\Routine\Condition\Diagnostics\InformationItem(new Name('x'), M\Routine\Condition\Diagnostics\StatementItemName::Number);
        $get = new M\Routine\Condition\Diagnostics\GetDiagnostics(new M\Routine\Condition\Diagnostics\StatementDiagnostics([$item]));
        $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), [$get])]);
        self::assertSame(M\Routine\Problem\ProgramRule::UndeclaredVariable, (new ConditionBindings())->find($body)[0]->rule ?? null);
        $this->expectExceptionMessage('Undeclared variable: x');
        new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
    }
}
