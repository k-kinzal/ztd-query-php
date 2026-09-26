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
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
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
}
