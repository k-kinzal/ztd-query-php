<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Plan;

use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef;
use Deriver\Deriver\Evaluator\Call\Plan\PlanValidation;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Call\State\StateSlot;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Plan\PlanValidation
 */
#[CoversClass(PlanValidation::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\Plan\PlanFootprints::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(Signature::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(Term::class)]
#[Small]
final class PlanValidationTest extends TestCase
{
    public function testValidateRejectsUndeclaredStateWrites(): void
    {
        $plan = new SemanticPlan([Action::write('example.slot', Expression::literal(Term::constant(1)))]);
        self::assertContains('undeclared-state-write', (new PlanValidation())->validate($plan));
    }
    public function testActionsRejectsSurplusOperandsBeforeTheyReachTheSolver(): void
    {
        $validation = new PlanValidation();
        $validation->actions([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return, [Expression::parameter('first'), Expression::parameter('second')])]);
        self::assertSame(['action-arity-or-opcode:return'], $validation->errors);
    }
    public function testExpressionRejectsMissingLiteralPayloads(): void
    {
        $validation = new PlanValidation();
        $validation->expression(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Constant));
        self::assertSame(['missing-constant'], $validation->errors);
    }
    public function testValidateRejectsMissingLocationBeforeCompilation(): void
    {
        $plan = new SemanticPlan([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::LocationWrite, [Expression::literal(Term::constant(1))])]);
        self::assertContains('action-location-or-arguments:location-write', (new PlanValidation())->validate($plan));
    }
    public function testExpressionRejectsMissingLocationPayload(): void
    {
        $validation = new PlanValidation();
        $validation->expression(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::LocationRead));
        self::assertContains('missing-location', $validation->errors);
    }
    public function testCompletesRequiresAllReferenceReturnBranches(): void
    {
        $validation = new PlanValidation();
        $return = Action::returnReference(LocationRef::parameter('value'));
        $condition = Expression::parameter('condition');
        self::assertFalse($validation->completes([Action::choice($condition, [$return], [])]));
        self::assertTrue($validation->completes([Action::choice($condition, [$return], [$return])]));
    }

    /**
     * @param Action $action
     * @param list<string> $writes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidActions')]
    public function testValidateAcceptsSupportedActionsWithTheirDeclaredEffects(Action $action, array $writes): void
    {
        self::assertSame([], (new PlanValidation())->validate(new SemanticPlan([$action], writes: $writes)));
    }

    /**
     * @return list<array{Action, list<string>}>
     */
    public static function providerValidActions(): array
    {
        $value = Expression::literal(Term::constant(1));
        $local = LocationRef::parameter('@value');
        return [
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return, [$value]), []],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Throw, [$value]), []],
            [Action::write('example.slot', $value), ['example.slot']],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [$value], 'value'), ['parameter:value']],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [$value], '@local'), []],
            [Action::choice($value, [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [$value], 'yes')], [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [$value], 'no')]), ['parameter:yes', 'parameter:no']],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Callback, [$value]), []],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Callback, [$value, $value]), []],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Invoke, [$value]), []],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Allocate, [$value]), []],
            [Action::assign($local, $value), []],
            [Action::alias($local, $local), []],
            [Action::returnReference($local), []],
            [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Havoc, locations: [$local], mayThrow: true), []],
        ];
    }

    /**
     * @param string $operation
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRequiredOperands')]
    public function testActionsRejectsMissingRequiredOperands(string $operation): void
    {
        $validation = new PlanValidation();
        $validation->actions([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::from($operation))]);
        self::assertSame(['action-arity-or-opcode:' . $operation], $validation->errors);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerRequiredOperands(): array
    {
        return [['return'], ['throw'], ['state-write'], ['write-parameter'], ['choice'], ['callback'], ['invoke'], ['allocate'], ['location-write']];
    }

    /**
     * @param Expression $expression
     * @param list<string> $reads
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidExpressions')]
    public function testExpressionChecksNestedOperandsAndRecordsStateReads(Expression $expression, array $reads): void
    {
        $validation = new PlanValidation();
        $validation->expression($expression);
        self::assertSame([], $validation->errors);
        self::assertSame($reads, $validation->reads);
    }

    /**
     * @return list<array{Expression, list<string>}>
     */
    public static function providerValidExpressions(): array
    {
        $input = Expression::parameter('value');
        return [
            [$input, []], [Expression::literal(Term::constant(null)), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::External, 'env:key'), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::State, 'example.slot', [$input]), ['example.slot']],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Binary, '+', [$input, $input]), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Unary, '!', [$input]), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Cast, 'int', [$input]), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::ArrayRead, operands: [$input, $input]), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::ArraySet, operands: [$input, $input, $input]), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, 'example.empty'), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, 'example.pair', [$input, $input]), []],
            [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::LocationRead, location: LocationRef::state('example.slot')), ['example.slot']],
        ];
    }

    public function testExpressionRejectsMalformedNestedOperands(): void
    {
        $validation = new PlanValidation();
        $validation->expression(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, 'example', [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Binary)]));
        $validation->expression(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Cast));
        self::assertSame(['expression-arity-or-opcode:binary', 'expression-arity-or-opcode:cast'], $validation->errors);
    }

    public function testValidateChecksReferenceCompletionAndBothKindsOfDeclaredFootprint(): void
    {
        $read = new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::State, 'example.slot', [Expression::receiver()]);
        $plan = new SemanticPlan([Action::returns($read)]);
        self::assertSame(['undeclared-state-read'], (new PlanValidation())->validate($plan));
        self::assertSame(['missing-reference-completion'], (new PlanValidation())->validate(new SemanticPlan([]), new Signature(byReference: true)));
        self::assertSame([], (new PlanValidation())->validate(new SemanticPlan([Action::returns($read)], reads: ['example.slot'])));
    }

    public function testValidateRequiresRegisteredStateSlotsAndKeepsParameterEffectsSeparate(): void
    {
        $plan = new SemanticPlan([Action::write('example.slot', Expression::parameter('value'))], writes: ['example.slot']);
        self::assertSame(['unregistered-state-slot:example.slot'], (new PlanValidation())->validate($plan, state: []));
        self::assertSame([], (new PlanValidation())->validate($plan, state: ['example.slot' => new StateSlot('example.slot')]));
        $reference = new SemanticPlan([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [Expression::parameter('value')], 'value')], writes: ['parameter:value']);
        self::assertSame([], (new PlanValidation())->validate($reference, state: []));
    }

    public function testActionsEnforcesTheExactPlanNodeAllowance(): void
    {
        $validation = new PlanValidation();
        $validation->nodes = 19999;
        $validation->actions([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Havoc)]);
        self::assertSame([], $validation->errors);
        self::assertSame(20000, $validation->nodes);
        $validation->actions([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Havoc)]);
        self::assertSame(['plan-node-budget'], $validation->errors);
    }

    public function testExpressionEnforcesTheExactPlanNodeAllowance(): void
    {
        $validation = new PlanValidation();
        $validation->nodes = 19999;
        $validation->expression(Expression::parameter('value'));
        self::assertSame([], $validation->errors);
        $validation->expression(Expression::parameter('value'));
        self::assertSame(['plan-node-budget'], $validation->errors);
    }

    public function testCompletesRetainsFallthroughAndAcceptsExplicitTermination(): void
    {
        $validation = new PlanValidation();
        self::assertFalse($validation->completes([]));
        self::assertFalse($validation->completes([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Havoc)]));
        self::assertTrue($validation->completes([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return)]));
        self::assertTrue($validation->completes([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Throw)]));
        $validation->nodes = 19999;
        self::assertTrue($validation->completes([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::ReturnReference)]));
        self::assertFalse($validation->completes([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::ReturnReference)]));
        self::assertSame(['plan-node-budget'], $validation->errors);
    }
}
