<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Plan;

use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\CallArgument;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef;
use Deriver\Deriver\Evaluator\Call\Plan\PlanFootprints;
use Deriver\Deriver\Evaluator\Call\Plan\PlanValidation;
use Deriver\Deriver\Evaluator\Declaration\Parameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Plan\PlanFootprints
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
        (new PlanFootprints($validation))->action(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Allocate, referenceResult: true));
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
        (new PlanFootprints($validation))->location(new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Parameter, 'x', parent: LocationRef::parameter('other')), true);
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
        self::assertFalse($footprints->shape(new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Parameter, 'name', receiver: Expression::receiver())));
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
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Parameter), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Parameter, 'x', receiver: $value), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Parameter, 'x', key: $value), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::State, receiver: $value), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::State, 'example.slot'), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::State, 'example.slot', $value), true],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::State, 'example.slot', $value, $parent), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::State, 'example.slot', $value, key: $value), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Element), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Element, parent: $parent), true],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Element, 'name', parent: $parent), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Element, receiver: $value, parent: $parent), false],
            [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::Element, parent: $parent, key: $value), true],
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
        (new PlanFootprints($validation))->location(LocationRef::element(LocationRef::parameter('items'), new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Cast)), false);
        self::assertSame(['expression-arity-or-opcode:cast'], $validation->errors);
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
        $arguments = [new CallArgument(LocationRef::parameter('value')), new CallArgument(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::State, 'example.slot', [Expression::receiver()]))];
        (new PlanFootprints($validation))->action(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Invoke, arguments: $arguments, referenceResult: true));
        self::assertSame(['parameter:value'], $validation->writes);
        self::assertSame(['example.slot'], $validation->reads);
        self::assertSame([], $validation->errors);
    }

    public function testActionRejectsNamedUnpackAndBranchesOnAnOrdinaryEffect(): void
    {
        $validation = new PlanValidation();
        $value = Expression::parameter('value');
        $footprints = new PlanFootprints($validation);
        $footprints->action(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Invoke, arguments: [new CallArgument($value, 'named', true)], no: [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return)]));
        self::assertSame(['unexpected-action-branches', 'named-unpack'], $validation->errors);
    }

    public function testActionRejectsArgumentsAndLocationsOnUnrelatedActions(): void
    {
        $validation = new PlanValidation();
        $value = Expression::parameter('value');
        $footprints = new PlanFootprints($validation);
        $footprints->action(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return, arguments: [new CallArgument($value)]));
        $footprints->action(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return, locations: [LocationRef::parameter('@local')]));
        self::assertSame(['action-location-or-arguments:return', 'action-location-or-arguments:return'], $validation->errors);
    }

    public function testCompletionKeepsOnlyDeclaredReferenceReturnsAndHavocThrows(): void
    {
        $validation = new PlanValidation();
        $footprints = new PlanFootprints($validation);
        $footprints->completion(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Havoc, mayThrow: true));
        $footprints->completion(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Invoke, referenceResult: true));
        self::assertSame([], $validation->errors);
        $footprints->completion(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return, mayThrow: true));
        $validation->signature = new Signature();
        $footprints->completion(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::ReturnReference));
        self::assertSame(['invalid-havoc-completion', 'undeclared-reference-return'], $validation->errors);
    }
}
