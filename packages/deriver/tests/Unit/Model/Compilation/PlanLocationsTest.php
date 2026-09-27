<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Compilation;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanLocations;
use Deriver\Model\Plan\Expression;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Compilation\PlanLocations
 */
#[CoversClass(PlanLocations::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class PlanLocationsTest extends TestCase
{
    public function testAddressPreservesAppendWithoutReadingStorage(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:test', 0, 1));
        $register = (new PlanLocations($compiler))->address(LocationRef::element(LocationRef::parameter('items')));
        self::assertSame('m1', $register);
        self::assertSame(['local', 'element-address'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame(['m0', ''], $compiler->instructions[0][1]->operands);
    }
    public function testAddressRejectsMalformedDescriptors(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:test', 0, 1));
        $this->expectException(InvalidInputException::class);
        (new PlanLocations($compiler))->address(new LocationRef('invalid'));
    }
    public function testFromExpressionRecognizesOnlyAddressableExpressions(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:test', 0, 1));
        $locations = new PlanLocations($compiler);
        self::assertSame('state', $locations->fromExpression(Expression::state('domain.slot'))?->kind);
        self::assertNull($locations->fromExpression(Expression::literal(Term::constant(1))));
    }

    #[DataProvider('providerIncompleteLocations')]
    public function testAddressRejectsMissingLocationComponents(LocationRef $location): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:location', 0, 1));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: invalid location '.$location->kind);
        (new PlanLocations($compiler))->address($location);
    }

    /**
     * @return iterable<string,array{LocationRef}>
     */
    public static function providerIncompleteLocations(): iterable
    {
        yield 'state missing receiver' => [new LocationRef('state', 'domain.slot')];
        yield 'element missing parent' => [new LocationRef('element', key:Expression::literal(Term::constant(0)))];
        yield 'unrecognized with otherwise valid inputs' => [new LocationRef('unknown', 'slot', Expression::receiver(), LocationRef::parameter('items'), Expression::literal(Term::constant(0)))];
    }

    public function testAddressBindsNestedElementsToTheirParentsAndEvaluatesKeysInOrder(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:location', 0, 1));
        $location = LocationRef::element(LocationRef::element(LocationRef::parameter('items'), Expression::literal(Term::constant('outer'))), Expression::literal(Term::constant(7)));
        self::assertSame('m4', (new PlanLocations($compiler))->address($location));
        self::assertSame(['local','constant','element-address','constant','element-address'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame('items', $compiler->instructions[0][0]->name);
        self::assertSame('outer', $compiler->instructions[0][1]->constant?->literal);
        self::assertSame(7, $compiler->instructions[0][3]->constant?->literal);
        self::assertSame(['m0','m1'], $compiler->instructions[0][2]->operands);
        self::assertSame(['m2','m3'], $compiler->instructions[0][4]->operands);
    }

    public function testAddressPreservesAnExplicitStateReceiverAndSlot(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:location', 0, 1));
        $receiver = new Term('object', 'receiver');
        self::assertSame('m1', (new PlanLocations($compiler))->address(LocationRef::state('custom.value', Expression::literal($receiver))));
        self::assertSame($receiver, $compiler->instructions[0][0]->constant);
        self::assertSame('model-state-address', $compiler->instructions[0][1]->operation);
        self::assertSame(['m0'], $compiler->instructions[0][1]->operands);
        self::assertSame('custom.value', $compiler->instructions[0][1]->name);
    }

    public function testFromExpressionRetainsExistingLocationIdentityWithoutEmittingInstructions(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:location', 0, 1));
        $location = LocationRef::element(LocationRef::parameter('items'));
        $locations = new PlanLocations($compiler);
        self::assertSame($location, $locations->fromExpression($location));
        self::assertSame($location, $locations->fromExpression(Expression::read($location)));
        self::assertSame([0 => []], $compiler->instructions);
    }

    public function testFromExpressionDefaultsMissingStateReceiverToThis(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:location', 0, 1));
        $location = (new PlanLocations($compiler))->fromExpression(new Expression('state', 'cache.value'));
        self::assertNotNull($location);
        self::assertSame('state', $location->kind);
        self::assertSame('cache.value', $location->name);
        self::assertNotNull($location->receiver);
        self::assertSame('parameter', $location->receiver->operation);
        self::assertSame('this', $location->receiver->name);
    }
}
