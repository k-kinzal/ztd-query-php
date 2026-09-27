<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Compilation;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Compilation\PlanFootprints;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\CallArgument;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Compilation\PlanFootprints
 */
#[CoversClass(PlanFootprints::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(Action::class)]
#[UsesClass(CallArgument::class)]
#[UsesClass(Expression::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Term::class)]
#[Small]
final class PlanFootprintsTest extends TestCase
{
    public function testActionRejectsReferenceResultOnAllocation(): void
    {
        $validation = new PlanValidation();
        (new PlanFootprints($validation))->action(new Action('allocate', referenceResult: true));
        self::assertContains('invalid-reference-result', $validation->errors);
    }
    public function testLocationRecordsContainerReadBeforeElementWrite(): void
    {
        $validation = new PlanValidation();
        (new PlanFootprints($validation))->location(LocationRef::element(LocationRef::state('domain.items'), Expression::literal(Term::constant('key'))), true);
        self::assertSame(['domain.items'], $validation->reads);
        self::assertSame(['domain.items'], $validation->writes);
    }
    public function testLocationRejectsContradictoryFields(): void
    {
        $validation = new PlanValidation();
        (new PlanFootprints($validation))->location(new LocationRef('parameter', 'x', parent: LocationRef::parameter('other')), true);
        self::assertSame(['invalid-location:parameter'], $validation->errors);
    }
    public function testExternalDistinguishesReferenceParametersAndLocals(): void
    {
        $validation = new PlanValidation();
        $validation->signature = new Signature([new Parameter('value', byReference: true), new Parameter('copy')]);
        $footprints = new PlanFootprints($validation);
        self::assertTrue($footprints->external('value'));
        self::assertFalse($footprints->external('copy'));
        self::assertFalse($footprints->external('result'));
    }
    public function testShapeRejectsAnUnknownKindEvenWhenItHasAName(): void
    {
        $footprints = new PlanFootprints(new PlanValidation());
        self::assertFalse($footprints->shape(new LocationRef('global', 'name')));
        self::assertTrue($footprints->shape(LocationRef::parameter('name')));
    }
    public function testCompletionRequiresAStorageReturnForReferenceSignatures(): void
    {
        $validation = new PlanValidation();
        $validation->signature = new Signature(byReference: true);
        (new PlanFootprints($validation))->completion(Action::returns(Expression::literal(Term::constant(1))));
        self::assertContains('reference-return-requires-location', $validation->errors);
    }

    /**
     * @param LocationRef $location
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLocationShapes')]
    public function testShapeRequiresTheFieldsOfItsOwnAddressKind(LocationRef $location, bool $expected): void
    {
        self::assertSame($expected, (new PlanFootprints(new PlanValidation()))->shape($location));
    }

    /**
     * @return list<array{LocationRef, bool}>
     */
    public static function providerLocationShapes(): array
    {
        $value = Expression::parameter('value');
        $parent = LocationRef::parameter('array');
        return [
            [new LocationRef('parameter'), false],
            [new LocationRef('parameter', 'x', receiver: $value), false],
            [new LocationRef('parameter', 'x', key: $value), false],
            [new LocationRef('state', receiver: $value), false],
            [new LocationRef('state', 'example.slot'), false],
            [new LocationRef('state', 'example.slot', $value), true],
            [new LocationRef('state', 'example.slot', $value, $parent), false],
            [new LocationRef('state', 'example.slot', $value, key: $value), false],
            [new LocationRef('element'), false],
            [new LocationRef('element', parent: $parent), true],
            [new LocationRef('element', 'name', parent: $parent), false],
            [new LocationRef('element', receiver: $value, parent: $parent), false],
            [new LocationRef('element', parent: $parent, key: $value), true],
        ];
    }

    public function testExternalKeepsUndeclaredLocalsPrivateAndLegacyParametersExternal(): void
    {
        $footprints = new PlanFootprints(new PlanValidation());
        self::assertTrue($footprints->external('value'));
        self::assertFalse($footprints->external('@result'));
    }

    public function testLocationTracksReadOnlyNestedReferencesAndAppendWrites(): void
    {
        $validation = new PlanValidation();
        $footprints = new PlanFootprints($validation);
        $footprints->location(LocationRef::element(LocationRef::state('example.items')), false);
        $footprints->location(LocationRef::parameter('input'), false);
        $footprints->location(LocationRef::parameter('@temporary'), true);
        self::assertSame(['example.items'], $validation->reads);
        self::assertSame([], $validation->writes);
        self::assertSame([], $validation->errors);
        $footprints->location(LocationRef::parameter('input'), true);
        self::assertSame(['parameter:input'], $validation->writes);
    }

    public function testLocationValidatesKeyExpressionsBeforeCompilation(): void
    {
        $validation = new PlanValidation();
        (new PlanFootprints($validation))->location(LocationRef::element(LocationRef::parameter('items'), new Expression('invalid')), false);
        self::assertSame(['expression-arity-or-opcode:invalid'], $validation->errors);
    }

    public function testLocationEnforcesTheExactPlanNodeAllowance(): void
    {
        $validation = new PlanValidation();
        $validation->nodes = 19999;
        $footprints = new PlanFootprints($validation);
        $footprints->location(LocationRef::parameter('input'), false);
        self::assertSame([], $validation->errors);
        $footprints->location(LocationRef::parameter('input'), false);
        self::assertSame(['plan-node-budget'], $validation->errors);
    }

    public function testActionKeepsInvocationArgumentsAndTheirReferenceEffects(): void
    {
        $validation = new PlanValidation();
        $arguments = [new CallArgument(LocationRef::parameter('value')), new CallArgument(new Expression('state', 'example.slot', [Expression::receiver()]))];
        (new PlanFootprints($validation))->action(new Action('invoke', arguments: $arguments, referenceResult: true));
        self::assertSame(['parameter:value'], $validation->writes);
        self::assertSame(['example.slot'], $validation->reads);
        self::assertSame([], $validation->errors);
    }

    public function testActionRejectsNamedUnpackAndBranchesOnAnOrdinaryEffect(): void
    {
        $validation = new PlanValidation();
        $value = Expression::parameter('value');
        $footprints = new PlanFootprints($validation);
        $footprints->action(new Action('invoke', arguments: [new CallArgument($value, 'named', true)], no: [new Action('return')]));
        self::assertSame(['unexpected-action-branches', 'named-unpack'], $validation->errors);
    }

    public function testActionRejectsArgumentsAndLocationsOnUnrelatedActions(): void
    {
        $validation = new PlanValidation();
        $value = Expression::parameter('value');
        $footprints = new PlanFootprints($validation);
        $footprints->action(new Action('return', arguments: [new CallArgument($value)]));
        $footprints->action(new Action('return', locations: [LocationRef::parameter('@local')]));
        self::assertSame(['action-location-or-arguments:return', 'action-location-or-arguments:return'], $validation->errors);
    }

    public function testCompletionKeepsOnlyDeclaredReferenceReturnsAndHavocThrows(): void
    {
        $validation = new PlanValidation();
        $footprints = new PlanFootprints($validation);
        $footprints->completion(new Action('havoc', mayThrow: true));
        $footprints->completion(new Action('invoke', referenceResult: true));
        self::assertSame([], $validation->errors);
        $footprints->completion(new Action('return', mayThrow: true));
        $validation->signature = new Signature();
        $footprints->completion(new Action('return-reference'));
        self::assertSame(['invalid-havoc-completion', 'undeclared-reference-return'], $validation->errors);
    }
}
