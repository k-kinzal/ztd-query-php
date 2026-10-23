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

    public function testInstructionChecksTheCursorBeforeItsFetchTargets(): void
    {
        $fetch = new M\Routine\Program\Cursor\FetchCursor(new Name('C'), [new Name('X')]);
        $rule = new ConditionBindings();
        self::assertSame(M\Routine\Problem\ProgramRule::UndefinedCursor, $rule->instruction($fetch, [], [])[0]->rule ?? null);
        self::assertSame(M\Routine\Problem\ProgramRule::UndeclaredVariable, $rule->instruction($fetch, [], [], [new Name('c')])[0]->rule ?? null);
        self::assertNull($rule->instruction($fetch, [new Name('x')], [], [new Name('c')]));
    }

    public function testFindRejectsAnUnboundCursorInAnInactiveBranchBeforeOperationConstruction(): void
    {
        $open = new M\Routine\Program\Cursor\OpenCursor(new Name('c'));
        $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), [$open])]);
        self::assertSame($open, (new ConditionBindings())->find($body)[1] ?? null);
        $this->expectExceptionMessage('Undefined CURSOR: c');
        new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
    }

    public function testHandlerResolvesNamesBeforeCheckingDuplicateConditionValues(): void
    {
        $code = new M\Routine\Condition\ErrorCode(new M\Literal\Numeral('1051'));
        $named = new M\Routine\Condition\ConditionName(new Name('C'));
        $handler = new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [$code, $named], new M\Routine\Program\Block());
        $handled = [];
        self::assertSame(M\Routine\Problem\ProgramRule::DuplicateHandler, (new ConditionBindings())->handler($handler, [(new \SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights())->key('c') => $code], $handled)[0]->rule ?? null);
        $body = new M\Routine\Program\Block([new M\Routine\Program\ConditionDeclaration(new Name('c'), $code), $handler]);
        $this->expectExceptionMessage('Duplicate handler declared in the same block');
        new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
    }

    public function testHandlerDoesNotMergeNumericCodesAndTheirSqlStateClasses(): void
    {
        $code = new M\Routine\Condition\ErrorCode(new M\Literal\Numeral('1051'));
        $state = new M\Routine\Condition\SqlState(new M\Literal\Text('42S02'));
        $handler = new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [$code, $state], new M\Routine\Program\Block());
        $handled = [];
        self::assertNull((new ConditionBindings())->handler($handler, [], $handled));
        self::assertSame([$code, $state], $handled);
        $inner = new M\Routine\Program\HandlerDeclaration(M\Routine\Program\HandlerAction::Continue, [new M\Routine\Condition\ErrorCode(new M\Literal\Numeral('1051'))], new M\Routine\Program\Block());
        $body = new M\Routine\Program\Block([$handler], [new M\Routine\Program\Block([$inner])]);
        self::assertSame($body, (new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body))->body);
    }
}
