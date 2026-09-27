<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Compilation;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Signature;
use Deriver\Model\State\StateSlot;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Compilation\PlanValidation
 */
#[CoversClass(PlanValidation::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
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
    public function testActionsRejectsUnknownOpcodesBeforeTheyReachTheSolver(): void
    {
        $validation = new PlanValidation();
        $validation->actions([new Action('host-eval')]);
        self::assertSame(['action-arity-or-opcode:host-eval'], $validation->errors);
    }
    public function testExpressionRejectsMissingLiteralPayloads(): void
    {
        $validation = new PlanValidation();
        $validation->expression(new Expression('constant'));
        self::assertSame(['missing-constant'], $validation->errors);
    }
    public function testValidateRejectsMissingLocationBeforeCompilation(): void
    {
        $plan = new SemanticPlan([new Action('location-write', [Expression::literal(Term::constant(1))])]);
        self::assertContains('action-location-or-arguments:location-write', (new PlanValidation())->validate($plan));
    }
    public function testExpressionRejectsMissingLocationPayload(): void
    {
        $validation = new PlanValidation();
        $validation->expression(new Expression('location-read'));
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
            [new Action('return', [$value]), []],
            [new Action('throw', [$value]), []],
            [Action::write('example.slot', $value), ['example.slot']],
            [new Action('write-parameter', [$value], 'value'), ['parameter:value']],
            [new Action('write-parameter', [$value], '@local'), []],
            [Action::choice($value, [new Action('write-parameter', [$value], 'yes')], [new Action('write-parameter', [$value], 'no')]), ['parameter:yes', 'parameter:no']],
            [new Action('callback', [$value]), []],
            [new Action('callback', [$value, $value]), []],
            [new Action('invoke', [$value]), []],
            [new Action('allocate', [$value]), []],
            [Action::assign($local, $value), []],
            [Action::alias($local, $local), []],
            [Action::returnReference($local), []],
            [new Action('havoc', locations: [$local], mayThrow: true), []],
        ];
    }

    /**
     * @param string $operation
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRequiredOperands')]
    public function testActionsRejectsMissingRequiredOperands(string $operation): void
    {
        $validation = new PlanValidation();
        $validation->actions([new Action($operation)]);
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
            [new Expression('external', 'env:key'), []],
            [new Expression('state', 'example.slot', [$input]), ['example.slot']],
            [new Expression('binary', '+', [$input, $input]), []],
            [new Expression('unary', '!', [$input]), []],
            [new Expression('cast', 'int', [$input]), []],
            [new Expression('array-read', operands: [$input, $input]), []],
            [new Expression('array-set', operands: [$input, $input, $input]), []],
            [new Expression('intrinsic', 'example.empty'), []],
            [new Expression('intrinsic', 'example.pair', [$input, $input]), []],
            [new Expression('location-read', location: LocationRef::state('example.slot')), ['example.slot']],
        ];
    }

    public function testExpressionRejectsUnknownOperationsAndMalformedNestedOperands(): void
    {
        $validation = new PlanValidation();
        $validation->expression(new Expression('intrinsic', 'example', [new Expression('host-eval')]));
        $validation->expression(new Expression('cast'));
        self::assertSame(['expression-arity-or-opcode:host-eval', 'expression-arity-or-opcode:cast'], $validation->errors);
    }

    public function testValidateChecksReferenceCompletionAndBothKindsOfDeclaredFootprint(): void
    {
        $read = new Expression('state', 'example.slot', [Expression::receiver()]);
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
        $reference = new SemanticPlan([new Action('write-parameter', [Expression::parameter('value')], 'value')], writes: ['parameter:value']);
        self::assertSame([], (new PlanValidation())->validate($reference, state: []));
    }

    public function testActionsEnforcesTheExactPlanNodeAllowance(): void
    {
        $validation = new PlanValidation();
        $validation->nodes = 19999;
        $validation->actions([new Action('havoc')]);
        self::assertSame([], $validation->errors);
        self::assertSame(20000, $validation->nodes);
        $validation->actions([new Action('havoc')]);
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
        self::assertFalse($validation->completes([new Action('havoc')]));
        self::assertTrue($validation->completes([new Action('return')]));
        self::assertTrue($validation->completes([new Action('throw')]));
        $validation->nodes = 19999;
        self::assertTrue($validation->completes([new Action('return-reference')]));
        self::assertFalse($validation->completes([new Action('return-reference')]));
        self::assertSame(['plan-node-budget'], $validation->errors);
    }
}
