<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ProgramScope::class)]
#[Medium]
final class ProgramScopeTest extends TestCase
{
    public function testHoldsComparesNamesWithoutRegardToLetterCase(): void
    {
        $scope = new ProgramScope(new Environment(new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')])));

        self::assertTrue($scope->holds([new Name('a'), new Name('Label')], new Name('LABEL')));
        self::assertFalse($scope->holds([new Name('a')], new Name('b')));
        self::assertFalse($scope->holds([], new Name('a')));
    }

    public function testVariableIsFalseInAnEnvironmentWithoutVariables(): void
    {
        $scope = new ProgramScope(new Environment(new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')])), ProgramKind::Procedure);

        self::assertFalse($scope->variable(new Name('x')));
    }

    public function testVariableFindsAParameterInAnyLetterCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze('CREATE PROCEDURE p(x INT) GET DIAGNOSTICS X = NUMBER')->facts->diagnostics);
        self::assertSame(['Undeclared variable: y'], array_map(static fn (Diagnostic $problem): string => $problem->message(), $semantics->analyze('CREATE PROCEDURE p(x INT) GET DIAGNOSTICS y = NUMBER')->facts->diagnostics));
    }

    public function testConditionFindsTheInnermostDeclarationOfTheName(): void
    {
        $outer = new ConditionDeclaration(new Name('c'), new ErrorCode(new Numeral('1051')));
        $inner = new ConditionDeclaration(new Name('C'), new SqlState(new Text('45000')));
        $scope = new ProgramScope(new Environment(new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')])), ProgramKind::Procedure, conditions: [$outer, $inner]);

        self::assertSame($inner, $scope->condition(new Name('c')));
        self::assertNull($scope->condition(new Name('d')));
    }

    public function testConditionLetsAnInnerBlockHideAnOuterCondition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $hidden = $semantics->analyze("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c; END; END");
        $hiding = $semantics->analyze("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN DECLARE c CONDITION FOR 1051; SIGNAL c; END; END");

        self::assertSame([], $hidden->facts->diagnostics);
        self::assertSame(['SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE'], array_map(static fn (Diagnostic $problem): string => $problem->message(), $hiding->facts->diagnostics));
    }

    public function testLabeledAddsTheLabelOfABlockOrALoop(): void
    {
        $scope = new ProgramScope(new Environment(new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')])), ProgramKind::Procedure);
        $block = $scope->labeled(new Name('b'), false);
        $loop = $block->labeled(new Name('l'), true);

        self::assertEquals([new Name('b')], $block->blocks);
        self::assertSame([], $block->loops);
        self::assertEquals([new Name('b')], $loop->blocks);
        self::assertEquals([new Name('l')], $loop->loops);
        self::assertSame(ProgramKind::Procedure, $loop->kind);
        self::assertSame($scope->environment, $loop->environment);
    }

    public function testLabeledKeepsTheScopeWithoutALabel(): void
    {
        $scope = new ProgramScope(new Environment(new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')])));

        self::assertSame($scope, $scope->labeled(null, true));
    }

    public function testDeclaredReplacesTheEnvironmentConditionsAndCursors(): void
    {
        $context = new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')]);
        $condition = new ConditionDeclaration(new Name('c'), new ErrorCode(new Numeral('1051')));
        $scope = (new ProgramScope(new Environment($context), ProgramKind::Trigger, time: TriggerTime::Before, event: TriggerEvent::Insert))->labeled(new Name('b'), false);
        $inner = new Environment($context, $scope->environment);
        $declared = $scope->declared($inner, [$condition], [new Name('cur')]);

        self::assertSame($inner, $declared->environment);
        self::assertSame([$condition], $declared->conditions);
        self::assertEquals([new Name('cur')], $declared->cursors);
        self::assertEquals([new Name('b')], $declared->blocks);
        self::assertSame(ProgramKind::Trigger, $declared->kind);
        self::assertSame(TriggerTime::Before, $declared->time);
        self::assertSame(TriggerEvent::Insert, $declared->event);
    }

    public function testHandlerSeesNoLabelOutsideTheHandler(): void
    {
        $condition = new ConditionDeclaration(new Name('c'), new ErrorCode(new Numeral('1051')));
        $scope = (new ProgramScope(new Environment(new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')])), ProgramKind::Procedure, conditions: [$condition], cursors: [new Name('cur')]))->labeled(new Name('b'), false)->labeled(new Name('l'), true);
        $handler = $scope->handler();

        self::assertSame([], $handler->blocks);
        self::assertSame([], $handler->loops);
        self::assertSame([$condition], $handler->conditions);
        self::assertEquals([new Name('cur')], $handler->cursors);
        self::assertSame(['LEAVE with no matching label: l'], array_map(static fn (Diagnostic $problem): string => $problem->message(), (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() l: BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION LEAVE l; END')->facts->diagnostics));
    }
}
