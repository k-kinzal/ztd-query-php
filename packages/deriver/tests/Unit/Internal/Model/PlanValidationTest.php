<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\PlanValidation
 */
#[CoversClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PlanValidationTest extends TestCase
{
    public function testValidateRejectsUndeclaredStateWrites(): void
    {
        $plan = new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::write('example.slot', \Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1)))]);
        self::assertContains('undeclared-state-write', (new \Deriver\Internal\Model\PlanValidation())->validate($plan));
    }
    public function testActionsRejectsUnknownOpcodesBeforeTheyReachTheSolver(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->actions([new \Deriver\Model\Plan\Action('host-eval')]);
        self::assertSame(['action-arity-or-opcode:host-eval'], $validation->errors);
    }
    public function testExpressionRejectsMissingLiteralPayloads(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->expression(new \Deriver\Model\Plan\Expression('constant'));
        self::assertSame(['missing-constant'], $validation->errors);
    }
    public function testValidateRejectsMissingLocationBeforeCompilation(): void
    {
        $plan = new \Deriver\Model\Plan\SemanticPlan([new \Deriver\Model\Plan\Action('location-write', [\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1))])]);
        self::assertContains('action-location-or-arguments:location-write', (new \Deriver\Internal\Model\PlanValidation())->validate($plan));
    }
    public function testExpressionRejectsMissingLocationPayload(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->expression(new \Deriver\Model\Plan\Expression('location-read'));
        self::assertContains('missing-location', $validation->errors);
    }
    public function testCompletesRequiresAllReferenceReturnBranches(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $return = \Deriver\Model\Plan\Action::returnReference(\Deriver\Model\Binding\LocationRef::parameter('value'));
        $condition = \Deriver\Model\Plan\Expression::parameter('condition');
        self::assertFalse($validation->completes([\Deriver\Model\Plan\Action::choice($condition, [$return], [])]));
        self::assertTrue($validation->completes([\Deriver\Model\Plan\Action::choice($condition, [$return], [$return])]));
    }

    /**
     * @param \Deriver\Model\Plan\Action $action
     * @param list<string> $writes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidActions')]
    public function testValidateAcceptsSupportedActionsWithTheirDeclaredEffects(\Deriver\Model\Plan\Action $action, array $writes): void
    {
        self::assertSame([], (new \Deriver\Internal\Model\PlanValidation())->validate(new \Deriver\Model\Plan\SemanticPlan([$action], writes: $writes)));
    }

    /**
     * @return list<array{\Deriver\Model\Plan\Action, list<string>}>
     */
    public static function providerValidActions(): array
    {
        $value = \Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1));
        $local = \Deriver\Model\Binding\LocationRef::parameter('@value');
        return [
            [new \Deriver\Model\Plan\Action('return', [$value]), []],
            [new \Deriver\Model\Plan\Action('throw', [$value]), []],
            [\Deriver\Model\Plan\Action::write('example.slot', $value), ['example.slot']],
            [new \Deriver\Model\Plan\Action('write-parameter', [$value], 'value'), ['parameter:value']],
            [new \Deriver\Model\Plan\Action('write-parameter', [$value], '@local'), []],
            [\Deriver\Model\Plan\Action::choice($value, [new \Deriver\Model\Plan\Action('write-parameter', [$value], 'yes')], [new \Deriver\Model\Plan\Action('write-parameter', [$value], 'no')]), ['parameter:yes', 'parameter:no']],
            [new \Deriver\Model\Plan\Action('callback', [$value]), []],
            [new \Deriver\Model\Plan\Action('callback', [$value, $value]), []],
            [new \Deriver\Model\Plan\Action('invoke', [$value]), []],
            [new \Deriver\Model\Plan\Action('allocate', [$value]), []],
            [\Deriver\Model\Plan\Action::assign($local, $value), []],
            [\Deriver\Model\Plan\Action::alias($local, $local), []],
            [\Deriver\Model\Plan\Action::returnReference($local), []],
            [new \Deriver\Model\Plan\Action('havoc', locations: [$local], mayThrow: true), []],
        ];
    }

    /**
     * @param string $operation
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRequiredOperands')]
    public function testActionsRejectsMissingRequiredOperands(string $operation): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->actions([new \Deriver\Model\Plan\Action($operation)]);
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
     * @param \Deriver\Model\Plan\Expression $expression
     * @param list<string> $reads
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidExpressions')]
    public function testExpressionChecksNestedOperandsAndRecordsStateReads(\Deriver\Model\Plan\Expression $expression, array $reads): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->expression($expression);
        self::assertSame([], $validation->errors);
        self::assertSame($reads, $validation->reads);
    }

    /**
     * @return list<array{\Deriver\Model\Plan\Expression, list<string>}>
     */
    public static function providerValidExpressions(): array
    {
        $input = \Deriver\Model\Plan\Expression::parameter('value');
        return [
            [$input, []], [\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(null)), []],
            [new \Deriver\Model\Plan\Expression('external', 'env:key'), []],
            [new \Deriver\Model\Plan\Expression('state', 'example.slot', [$input]), ['example.slot']],
            [new \Deriver\Model\Plan\Expression('binary', '+', [$input, $input]), []],
            [new \Deriver\Model\Plan\Expression('unary', '!', [$input]), []],
            [new \Deriver\Model\Plan\Expression('cast', 'int', [$input]), []],
            [new \Deriver\Model\Plan\Expression('array-read', operands: [$input, $input]), []],
            [new \Deriver\Model\Plan\Expression('array-set', operands: [$input, $input, $input]), []],
            [new \Deriver\Model\Plan\Expression('intrinsic', 'example.empty'), []],
            [new \Deriver\Model\Plan\Expression('intrinsic', 'example.pair', [$input, $input]), []],
            [new \Deriver\Model\Plan\Expression('location-read', location: \Deriver\Model\Binding\LocationRef::state('example.slot')), ['example.slot']],
        ];
    }

    public function testExpressionRejectsUnknownOperationsAndMalformedNestedOperands(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->expression(new \Deriver\Model\Plan\Expression('intrinsic', 'example', [new \Deriver\Model\Plan\Expression('host-eval')]));
        $validation->expression(new \Deriver\Model\Plan\Expression('cast'));
        self::assertSame(['expression-arity-or-opcode:host-eval', 'expression-arity-or-opcode:cast'], $validation->errors);
    }

    public function testValidateChecksReferenceCompletionAndBothKindsOfDeclaredFootprint(): void
    {
        $read = new \Deriver\Model\Plan\Expression('state', 'example.slot', [\Deriver\Model\Plan\Expression::receiver()]);
        $plan = new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns($read)]);
        self::assertSame(['undeclared-state-read'], (new \Deriver\Internal\Model\PlanValidation())->validate($plan));
        self::assertSame(['missing-reference-completion'], (new \Deriver\Internal\Model\PlanValidation())->validate(new \Deriver\Model\Plan\SemanticPlan([]), new \Deriver\Model\Signature\Signature(byReference: true)));
        self::assertSame([], (new \Deriver\Internal\Model\PlanValidation())->validate(new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns($read)], reads: ['example.slot'])));
    }

    public function testValidateRequiresRegisteredStateSlotsAndKeepsParameterEffectsSeparate(): void
    {
        $plan = new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::write('example.slot', \Deriver\Model\Plan\Expression::parameter('value'))], writes: ['example.slot']);
        self::assertSame(['unregistered-state-slot:example.slot'], (new \Deriver\Internal\Model\PlanValidation())->validate($plan, state: new \Deriver\Internal\Model\StateRegistry([])));
        self::assertSame([], (new \Deriver\Internal\Model\PlanValidation())->validate($plan, state: new \Deriver\Internal\Model\StateRegistry([new \Deriver\Model\State\StateSlot('example.slot')])));
        $reference = new \Deriver\Model\Plan\SemanticPlan([new \Deriver\Model\Plan\Action('write-parameter', [\Deriver\Model\Plan\Expression::parameter('value')], 'value')], writes: ['parameter:value']);
        self::assertSame([], (new \Deriver\Internal\Model\PlanValidation())->validate($reference, state: new \Deriver\Internal\Model\StateRegistry([])));
    }

    public function testActionsEnforcesTheExactPlanNodeAllowance(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->nodes = 19999;
        $validation->actions([new \Deriver\Model\Plan\Action('havoc')]);
        self::assertSame([], $validation->errors);
        self::assertSame(20000, $validation->nodes);
        $validation->actions([new \Deriver\Model\Plan\Action('havoc')]);
        self::assertSame(['plan-node-budget'], $validation->errors);
    }

    public function testExpressionEnforcesTheExactPlanNodeAllowance(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->nodes = 19999;
        $validation->expression(\Deriver\Model\Plan\Expression::parameter('value'));
        self::assertSame([], $validation->errors);
        $validation->expression(\Deriver\Model\Plan\Expression::parameter('value'));
        self::assertSame(['plan-node-budget'], $validation->errors);
    }

    public function testCompletesRetainsFallthroughAndAcceptsExplicitTermination(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        self::assertFalse($validation->completes([]));
        self::assertFalse($validation->completes([new \Deriver\Model\Plan\Action('havoc')]));
        self::assertTrue($validation->completes([new \Deriver\Model\Plan\Action('return')]));
        self::assertTrue($validation->completes([new \Deriver\Model\Plan\Action('throw')]));
        $validation->nodes = 19999;
        self::assertTrue($validation->completes([new \Deriver\Model\Plan\Action('return-reference')]));
        self::assertFalse($validation->completes([new \Deriver\Model\Plan\Action('return-reference')]));
        self::assertSame(['plan-node-budget'], $validation->errors);
    }
}
