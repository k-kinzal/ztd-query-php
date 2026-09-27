<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Compilation;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Compilation\PlanActions;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Compilation\PlanActions
 */
#[CoversClass(PlanActions::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanInvocations::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanLocations::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class PlanActionsTest extends TestCase
{
    public function testApplyReturnsAReferenceThroughCoreMemory(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:test', 0, 1));
        self::assertTrue((new PlanActions($compiler))->apply(Action::returnReference(LocationRef::parameter('value'))));
        self::assertSame(['local', 'reference'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame('return', $compiler->terminators[0]->kind);
    }
    public function testApplyRejectsUnknownActions(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:test', 0, 1));
        $this->expectException(InvalidInputException::class);
        (new PlanActions($compiler))->apply(new Action('invalid'));
    }

    /**
     * @param list<string> $operations Expected control graph operations
     */
    #[DataProvider('providerCompletionActions')]
    public function testApplyBindsCompletionToItsExactValue(Action $action, string $kind, string $operand, array $operations): void
    {
        $source = new SourceRef('snapshot', 'model:completion', 7, 9);
        $compiler = new PlanCompiler($source);
        self::assertTrue((new PlanActions($compiler))->apply($action));
        self::assertSame($operations, array_column($compiler->instructions[0], 'operation'));
        self::assertSame($kind, $compiler->terminators[0]->kind);
        self::assertSame($operand, $compiler->terminators[0]->operand);
        self::assertSame([], $compiler->terminators[0]->targets);
    }

    /**
     * @return iterable<string,array{Action,string,string,list<string>}>
     */
    public static function providerCompletionActions(): iterable
    {
        yield 'return value' => [Action::returns(Expression::literal(Term::constant(17))),'return','m0',['constant']];
        yield 'throw value' => [Action::throws(Expression::literal(new Term('throwable', 'Error'))),'throw','m0',['constant']];
        yield 'return reference' => [Action::returnReference(LocationRef::parameter('input')),'return','m1',['local','reference']];
        yield 'bare return' => [new Action('return'),'return','',[]];
    }

    /**
     * @param list<string> $operations Expected storage graph operations
     */
    #[DataProvider('providerOrderedWrites')]
    public function testApplyPreservesTheWriteTargetAndOperandOrder(Action $action, array $operations, int $address, int $value, string $target): void
    {
        $source = new SourceRef('snapshot', 'model:write', 0, 8);
        $compiler = new PlanCompiler($source);
        self::assertFalse((new PlanActions($compiler))->apply($action));
        $instructions = $compiler->instructions[0];
        self::assertSame($operations, array_column($instructions, 'operation'));
        self::assertSame($target, $instructions[$address]->name);
        self::assertSame(47, $instructions[$value]->constant?->literal);
        self::assertSame([$instructions[$address]->result,$instructions[$value]->result], $instructions[count($instructions) - 1]->operands);
        self::assertSame($source, $instructions[count($instructions) - 1]->source);
        self::assertSame([], $compiler->terminators);
    }

    /**
     * @return iterable<string,array{Action,list<string>,int,int,string}>
     */
    public static function providerOrderedWrites(): iterable
    {
        $value = Expression::literal(Term::constant(47));
        yield 'receiver state' => [Action::write('cache.value', $value, Expression::literal(new Term('object', 'receiver'))),['constant','constant','model-state-address','write'],2,1,'cache.value'];
        yield 'reference parameter' => [new Action('write-parameter', [$value], 'output'),['constant','local','write'],1,0,'output'];
        yield 'explicit location' => [Action::assign(LocationRef::parameter('output'), $value),['local','constant','write'],0,1,'output'];
    }

    public function testApplyConnectsAStateAddressToItsReceiver(): void
    {
        $receiver = new Term('object', 'object-identity');
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:write', 0, 8));
        self::assertFalse((new PlanActions($compiler))->apply(Action::write('cache.value', Expression::literal(Term::constant(1)), Expression::literal($receiver))));
        self::assertSame($receiver, $compiler->instructions[0][0]->constant);
        self::assertSame(['m0'], $compiler->instructions[0][2]->operands);
    }

    #[DataProvider('providerHavocActions')]
    public function testApplyPreservesEveryHavocLocationAndExceptionalPossibility(bool $mayThrow): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:havoc', 0, 8));
        self::assertFalse((new PlanActions($compiler))->apply(Action::havoc([LocationRef::parameter('first'),LocationRef::parameter('second')], 'remote write', $mayThrow)));
        self::assertSame(['local','local','model-havoc'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame(['first','second','remote write'], array_column($compiler->instructions[0], 'name'));
        self::assertSame(['m0','m1'], $compiler->instructions[0][2]->operands);
        self::assertSame(['may-throw' => $mayThrow], $compiler->instructions[0][2]->attributes);
        self::assertSame([], $compiler->terminators);
    }

    /**
     * @return iterable<string,array{bool}>
     */
    public static function providerHavocActions(): iterable
    {
        yield 'normal only' => [false];
        yield 'possibly exceptional' => [true];
    }

    public function testApplyAliasesTheDestinationToTheSourceCell(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:alias', 0, 8));
        self::assertFalse((new PlanActions($compiler))->apply(Action::alias(LocationRef::parameter('destination'), LocationRef::parameter('source'))));
        self::assertSame(['destination','source',''], array_column($compiler->instructions[0], 'name'));
        self::assertSame(['local','local','alias'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame(['m0','m1'], $compiler->instructions[0][2]->operands);
    }

    public function testApplyKeepsBothChoiceCompletionsAndTheContinuationSeparate(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:choice', 0, 8));
        $action = Action::choice(Expression::literal(Term::parameter('flag', 'bool')), [Action::returns(Expression::literal(Term::constant(1)))], [Action::throws(Expression::literal(new Term('throwable', 'Error')))]);
        self::assertFalse((new PlanActions($compiler))->apply($action));
        self::assertSame(3, $compiler->current);
        self::assertSame('branch', $compiler->terminators[0]->kind);
        self::assertSame('m0', $compiler->terminators[0]->operand);
        self::assertSame([1,2], $compiler->terminators[0]->targets);
        self::assertSame('return', $compiler->terminators[1]->kind);
        self::assertSame('m1', $compiler->terminators[1]->operand);
        self::assertSame('throw', $compiler->terminators[2]->kind);
        self::assertSame('m2', $compiler->terminators[2]->operand);
        self::assertSame([], $compiler->instructions[3]);
    }

    #[DataProvider('providerInvocationActions')]
    public function testApplyRoutesEachInvocationThroughPreparedArgumentBinding(Action $action, string $operation): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'model:call', 0, 8));
        self::assertFalse((new PlanActions($compiler))->apply($action));
        self::assertSame(['constant','call-prepare',$operation,'local','write'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame($operation, $compiler->instructions[0][1]->attributes['call-operation']);
        self::assertSame(['prepared' => 'm1'], $compiler->instructions[0][2]->attributes);
        self::assertSame('@result', $compiler->instructions[0][3]->name);
        self::assertSame(['m3','m2'], $compiler->instructions[0][4]->operands);
        self::assertSame([], $compiler->terminators);
    }

    /**
     * @return iterable<string,array{Action,string}>
     */
    public static function providerInvocationActions(): iterable
    {
        $target = Expression::literal(Term::constant('target'));
        yield 'callback' => [Action::callback('@result', $target),'invoke'];
        yield 'invoke' => [Action::invoke('@result', $target),'invoke'];
        yield 'allocate' => [Action::allocate('@result', $target),'new'];
    }
}
