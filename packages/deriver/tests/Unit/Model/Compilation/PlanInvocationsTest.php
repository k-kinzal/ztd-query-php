<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Compilation;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanInvocations;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\CallArgument;
use Deriver\Model\Plan\Expression;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Compilation\PlanInvocations
 */
#[CoversClass(PlanInvocations::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanLocations::class)]
#[UsesClass(Action::class)]
#[UsesClass(CallArgument::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class PlanInvocationsTest extends TestCase
{
    public function testApplyPreparesCallBeforeItsArguments(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:test', 0, 1));
        (new PlanInvocations($compiler))->apply(Action::invoke('@result', Expression::literal(Term::constant('consume')), [new CallArgument(LocationRef::parameter('value'))], true));
        self::assertSame(['constant', 'call-prepare', 'local', 'argument', 'invoke', 'local', 'returned-address', 'alias'], array_column($compiler->instructions[0], 'operation'));
        self::assertTrue($compiler->instructions[0][3]->attributes['address']);
    }
    public function testArgumentsPreservesNamedUnpackAndLocationMetadata(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:test', 0, 1));
        $arguments = (new PlanInvocations($compiler))->arguments([new CallArgument(LocationRef::parameter('items'), unpack: true)], 'prepared');
        self::assertTrue($arguments[0]->unpack);
        self::assertSame($arguments[0]->register, $arguments[0]->location);
        self::assertTrue($compiler->instructions[0][1]->attributes['unpack-variable']);
    }

    /**
     * @param list<string> $operations Expected argument evaluation order
     */
    #[DataProvider('providerArgumentLocations')]
    public function testArgumentsDistinguishesAddressableStorageFromComputedValues(Expression|LocationRef $input, array $operations, bool $address, bool $unpackVariable): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:arguments', 0, 4));
        $arguments = (new PlanInvocations($compiler))->arguments([new CallArgument($input, 'items', true)], 'signature');
        $instructions = $compiler->instructions[0];
        $instruction = $instructions[count($instructions) - 1];
        self::assertSame($operations, array_column($instructions, 'operation'));
        self::assertSame(['signature',$instructions[count($instructions) - 2]->result], $instruction->operands);
        self::assertSame(['address' => $address,'argument-name' => 'items','unpack' => true,'unpack-variable' => $unpackVariable,'temporary' => false], $instruction->attributes);
        self::assertSame([], $instruction->arguments);
        self::assertCount(1, $arguments);
        self::assertSame($instruction->result, $arguments[0]->register);
        self::assertSame($instruction->result, $arguments[0]->location);
        self::assertSame('items', $arguments[0]->name);
        self::assertTrue($arguments[0]->unpack);
    }

    /**
     * @return iterable<string,array{Expression|LocationRef,list<string>,bool,bool}>
     */
    public static function providerArgumentLocations(): iterable
    {
        yield 'parameter location' => [LocationRef::parameter('value'),['local','argument'],true,true];
        yield 'parameter expression' => [Expression::parameter('value'),['local','argument'],true,true];
        yield 'state expression' => [Expression::state('cache.value'),['local','read','model-state-address','argument'],true,false];
        yield 'state location' => [LocationRef::state('cache.value'),['local','read','model-state-address','argument'],true,false];
        yield 'element location' => [LocationRef::element(LocationRef::parameter('value'), Expression::literal(Term::constant('key'))),['local','constant','element-address','argument'],true,false];
        yield 'explicit read' => [Expression::read(LocationRef::parameter('value')),['local','argument'],true,true];
        yield 'computed value' => [Expression::literal(Term::constant(4)),['constant','argument'],false,false];
    }

    public function testArgumentsSuppliesEarlierBindingsToEachLaterArgument(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:arguments', 0, 4));
        $arguments = (new PlanInvocations($compiler))->arguments([new CallArgument(Expression::literal(Term::constant(1))),new CallArgument(Expression::parameter('second'), 'other')], 'signature');
        self::assertCount(2, $arguments);
        self::assertSame([], $compiler->instructions[0][1]->arguments);
        self::assertSame([$arguments[0]], $compiler->instructions[0][3]->arguments);
        self::assertSame(['signature','m2'], $compiler->instructions[0][3]->operands);
        self::assertSame('other', $arguments[1]->name);
        self::assertFalse($arguments[1]->unpack);
        self::assertSame('m3', $arguments[1]->location);
    }

    public function testApplyPreservesCallbackArgumentOrderAndResultBinding(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:callback', 0, 4));
        $target = Term::constant('callback');
        (new PlanInvocations($compiler))->apply(Action::callback('result', Expression::literal($target), [Expression::literal(Term::constant(11)),Expression::literal(Term::constant(29))]));
        $instructions = $compiler->instructions[0];
        self::assertSame(['constant','call-prepare','constant','argument','constant','argument','invoke','local','write'], array_column($instructions, 'operation'));
        self::assertSame($target, $instructions[0]->constant);
        self::assertSame(11, $instructions[2]->constant?->literal);
        self::assertSame(29, $instructions[4]->constant?->literal);
        self::assertSame(['m0'], $instructions[6]->operands);
        self::assertSame(['m3','m5'], array_column($instructions[6]->arguments, 'register'));
        self::assertSame(['prepared' => 'm1'], $instructions[6]->attributes);
        self::assertSame('result', $instructions[7]->name);
        self::assertSame(['m7','m6'], $instructions[8]->operands);
    }

    public function testApplyRetainsTheReturnedCellWhenAReferenceResultIsRequested(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:reference', 0, 4));
        (new PlanInvocations($compiler))->apply(Action::invoke('alias', Expression::literal(Term::constant('byReference')), byReference:true));
        $instructions = $compiler->instructions[0];
        self::assertSame(['constant','call-prepare','invoke','local','returned-address','alias'], array_column($instructions, 'operation'));
        self::assertSame(['m2'], $instructions[4]->operands);
        self::assertSame(['m3','m4'], $instructions[5]->operands);
        self::assertSame('alias', $instructions[3]->name);
    }
}
