<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\PlanFootprints
 */
#[CoversClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PlanFootprintsTest extends TestCase
{
    public function testActionRejectsReferenceResultOnAllocation(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        (new \Deriver\Internal\Model\PlanFootprints($validation))->action(new \Deriver\Model\Plan\Action('allocate', referenceResult: true));
        self::assertContains('invalid-reference-result', $validation->errors);
    }
    public function testLocationRecordsContainerReadBeforeElementWrite(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        (new \Deriver\Internal\Model\PlanFootprints($validation))->location(\Deriver\Model\Binding\LocationRef::element(\Deriver\Model\Binding\LocationRef::state('domain.items'), \Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant('key'))), true);
        self::assertSame(['domain.items'], $validation->reads);
        self::assertSame(['domain.items'], $validation->writes);
    }
    public function testLocationRejectsContradictoryFields(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        (new \Deriver\Internal\Model\PlanFootprints($validation))->location(new \Deriver\Model\Binding\LocationRef('parameter', 'x', parent: \Deriver\Model\Binding\LocationRef::parameter('other')), true);
        self::assertSame(['invalid-location:parameter'], $validation->errors);
    }
    public function testExternalDistinguishesReferenceParametersAndLocals(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->signature = new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('value', byReference: true), new \Deriver\Model\Signature\Parameter('copy')]);
        $footprints = new \Deriver\Internal\Model\PlanFootprints($validation);
        self::assertTrue($footprints->external('value'));
        self::assertFalse($footprints->external('copy'));
        self::assertFalse($footprints->external('result'));
    }
    public function testShapeRejectsAnUnknownKindEvenWhenItHasAName(): void
    {
        $footprints = new \Deriver\Internal\Model\PlanFootprints(new \Deriver\Internal\Model\PlanValidation());
        self::assertFalse($footprints->shape(new \Deriver\Model\Binding\LocationRef('global', 'name')));
        self::assertTrue($footprints->shape(\Deriver\Model\Binding\LocationRef::parameter('name')));
    }
    public function testCompletionRequiresAStorageReturnForReferenceSignatures(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->signature = new \Deriver\Model\Signature\Signature(byReference: true);
        (new \Deriver\Internal\Model\PlanFootprints($validation))->completion(\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant(1))));
        self::assertContains('reference-return-requires-location', $validation->errors);
    }
}
