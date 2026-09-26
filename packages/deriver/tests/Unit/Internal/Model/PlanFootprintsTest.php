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
#[UsesClass(\Deriver\Model\Plan\CallArgument::class)]
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

    /**
     * @param \Deriver\Model\Binding\LocationRef $location
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLocationShapes')]
    public function testShapeRequiresTheFieldsOfItsOwnAddressKind(\Deriver\Model\Binding\LocationRef $location, bool $expected): void
    {
        self::assertSame($expected, (new \Deriver\Internal\Model\PlanFootprints(new \Deriver\Internal\Model\PlanValidation()))->shape($location));
    }

    /**
     * @return list<array{\Deriver\Model\Binding\LocationRef, bool}>
     */
    public static function providerLocationShapes(): array
    {
        $value = \Deriver\Model\Plan\Expression::parameter('value');
        $parent = \Deriver\Model\Binding\LocationRef::parameter('array');
        return [
            [new \Deriver\Model\Binding\LocationRef('parameter'), false],
            [new \Deriver\Model\Binding\LocationRef('parameter', 'x', receiver: $value), false],
            [new \Deriver\Model\Binding\LocationRef('parameter', 'x', key: $value), false],
            [new \Deriver\Model\Binding\LocationRef('state', receiver: $value), false],
            [new \Deriver\Model\Binding\LocationRef('state', 'example.slot'), false],
            [new \Deriver\Model\Binding\LocationRef('state', 'example.slot', $value), true],
            [new \Deriver\Model\Binding\LocationRef('state', 'example.slot', $value, $parent), false],
            [new \Deriver\Model\Binding\LocationRef('state', 'example.slot', $value, key: $value), false],
            [new \Deriver\Model\Binding\LocationRef('element'), false],
            [new \Deriver\Model\Binding\LocationRef('element', parent: $parent), true],
            [new \Deriver\Model\Binding\LocationRef('element', 'name', parent: $parent), false],
            [new \Deriver\Model\Binding\LocationRef('element', receiver: $value, parent: $parent), false],
            [new \Deriver\Model\Binding\LocationRef('element', parent: $parent, key: $value), true],
        ];
    }

    public function testExternalKeepsUndeclaredLocalsPrivateAndLegacyParametersExternal(): void
    {
        $footprints = new \Deriver\Internal\Model\PlanFootprints(new \Deriver\Internal\Model\PlanValidation());
        self::assertTrue($footprints->external('value'));
        self::assertFalse($footprints->external('@result'));
    }

    public function testLocationTracksReadOnlyNestedReferencesAndAppendWrites(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $footprints = new \Deriver\Internal\Model\PlanFootprints($validation);
        $footprints->location(\Deriver\Model\Binding\LocationRef::element(\Deriver\Model\Binding\LocationRef::state('example.items')), false);
        $footprints->location(\Deriver\Model\Binding\LocationRef::parameter('input'), false);
        $footprints->location(\Deriver\Model\Binding\LocationRef::parameter('@temporary'), true);
        self::assertSame(['example.items'], $validation->reads);
        self::assertSame([], $validation->writes);
        self::assertSame([], $validation->errors);
        $footprints->location(\Deriver\Model\Binding\LocationRef::parameter('input'), true);
        self::assertSame(['parameter:input'], $validation->writes);
    }

    public function testLocationValidatesKeyExpressionsBeforeCompilation(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        (new \Deriver\Internal\Model\PlanFootprints($validation))->location(\Deriver\Model\Binding\LocationRef::element(\Deriver\Model\Binding\LocationRef::parameter('items'), new \Deriver\Model\Plan\Expression('invalid')), false);
        self::assertSame(['expression-arity-or-opcode:invalid'], $validation->errors);
    }

    public function testLocationEnforcesTheExactPlanNodeAllowance(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $validation->nodes = 19999;
        $footprints = new \Deriver\Internal\Model\PlanFootprints($validation);
        $footprints->location(\Deriver\Model\Binding\LocationRef::parameter('input'), false);
        self::assertSame([], $validation->errors);
        $footprints->location(\Deriver\Model\Binding\LocationRef::parameter('input'), false);
        self::assertSame(['plan-node-budget'], $validation->errors);
    }

    public function testActionKeepsInvocationArgumentsAndTheirReferenceEffects(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $arguments = [new \Deriver\Model\Plan\CallArgument(\Deriver\Model\Binding\LocationRef::parameter('value')), new \Deriver\Model\Plan\CallArgument(new \Deriver\Model\Plan\Expression('state', 'example.slot', [\Deriver\Model\Plan\Expression::receiver()]))];
        (new \Deriver\Internal\Model\PlanFootprints($validation))->action(new \Deriver\Model\Plan\Action('invoke', arguments: $arguments, referenceResult: true));
        self::assertSame(['parameter:value'], $validation->writes);
        self::assertSame(['example.slot'], $validation->reads);
        self::assertSame([], $validation->errors);
    }

    public function testActionRejectsNamedUnpackAndBranchesOnAnOrdinaryEffect(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $value = \Deriver\Model\Plan\Expression::parameter('value');
        $footprints = new \Deriver\Internal\Model\PlanFootprints($validation);
        $footprints->action(new \Deriver\Model\Plan\Action('invoke', arguments: [new \Deriver\Model\Plan\CallArgument($value, 'named', true)], no: [new \Deriver\Model\Plan\Action('return')]));
        self::assertSame(['unexpected-action-branches', 'named-unpack'], $validation->errors);
    }

    public function testActionRejectsArgumentsAndLocationsOnUnrelatedActions(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $value = \Deriver\Model\Plan\Expression::parameter('value');
        $footprints = new \Deriver\Internal\Model\PlanFootprints($validation);
        $footprints->action(new \Deriver\Model\Plan\Action('return', arguments: [new \Deriver\Model\Plan\CallArgument($value)]));
        $footprints->action(new \Deriver\Model\Plan\Action('return', locations: [\Deriver\Model\Binding\LocationRef::parameter('@local')]));
        self::assertSame(['action-location-or-arguments:return', 'action-location-or-arguments:return'], $validation->errors);
    }

    public function testCompletionKeepsOnlyDeclaredReferenceReturnsAndHavocThrows(): void
    {
        $validation = new \Deriver\Internal\Model\PlanValidation();
        $footprints = new \Deriver\Internal\Model\PlanFootprints($validation);
        $footprints->completion(new \Deriver\Model\Plan\Action('havoc', mayThrow: true));
        $footprints->completion(new \Deriver\Model\Plan\Action('invoke', referenceResult: true));
        self::assertSame([], $validation->errors);
        $footprints->completion(new \Deriver\Model\Plan\Action('return', mayThrow: true));
        $validation->signature = new \Deriver\Model\Signature\Signature();
        $footprints->completion(new \Deriver\Model\Plan\Action('return-reference'));
        self::assertSame(['invalid-havoc-completion', 'undeclared-reference-return'], $validation->errors);
    }
}
