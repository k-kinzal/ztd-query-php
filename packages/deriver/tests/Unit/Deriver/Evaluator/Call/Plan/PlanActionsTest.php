<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Plan;

use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef;
use Deriver\Deriver\Evaluator\Call\Plan\PlanActions;
use Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Plan\PlanActions
 */
#[CoversClass(PlanActions::class)]
#[UsesClass(\Deriver\Deriver\Lowerer\Graph\Instruction::class)]
#[UsesClass(\Deriver\Deriver\Lowerer\Graph\Terminator::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\Plan\PlanInvocations::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\Plan\PlanLocations::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class PlanActionsTest extends TestCase
{
    public function testApplyReturnsAReferenceThroughCoreMemory(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'evaluator:test', 0, 1));
        self::assertTrue((new PlanActions($compiler))->apply(Action::returnReference(LocationRef::parameter('value'))));
        self::assertSame(['local', 'reference'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Return, $compiler->terminators[0]->kind);
    }
    public function testApplyRejectsMalformedStorageTargets(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'evaluator:test', 0, 1));
        $this->expectException(InvalidInputException::class);
        (new PlanActions($compiler))->apply(new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::LocationWrite, locations: [new LocationRef(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind::State)]));
    }

    /**
     * @param list<string> $operations Expected control graph operations
     */
    #[DataProvider('providerCompletionActions')]
    public function testApplyBindsCompletionToItsExactValue(Action $action, string $kind, string $operand, array $operations): void
    {
        $source = new SourceRef('snapshot', 'evaluator:completion', 7, 9);
        $compiler = new PlanCompiler($source);
        self::assertTrue((new PlanActions($compiler))->apply($action));
        self::assertSame($operations, array_column($compiler->instructions[0], 'operation'));
        self::assertSame(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::from($kind), $compiler->terminators[0]->kind);
        self::assertSame($operand, $compiler->terminators[0]->operand);
        self::assertSame([], $compiler->terminators[0]->targets);
    }

    /**
     * @return iterable<string,array{Action,string,string,list<string>}>
     */
    public static function providerCompletionActions(): iterable
    {
        yield 'return value' => [Action::returns(Expression::literal(Term::constant(17))),'return','m0',['constant']];
        yield 'throw value' => [Action::throws(Expression::literal(new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error'))),'throw','m0',['constant']];
        yield 'return reference' => [Action::returnReference(LocationRef::parameter('input')),'return','m1',['local','reference']];
        yield 'bare return' => [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::Return),'return','',[]];
    }

    /**
     * @param list<string> $operations Expected storage graph operations
     */
    #[DataProvider('providerOrderedWrites')]
    public function testApplyPreservesTheWriteTargetAndOperandOrder(Action $action, array $operations, int $address, int $value, string $target): void
    {
        $source = new SourceRef('snapshot', 'evaluator:write', 0, 8);
        $compiler = new PlanCompiler($source);
        self::assertFalse((new PlanActions($compiler))->apply($action));
        $instructions = $compiler->instructions[0];
        self::assertSame($operations, array_column($instructions, 'operation'));
        self::assertSame($target, $instructions[$address]->name);
        self::assertSame(47, (new \Deriver\Deriver\Evaluator\Expression\LiteralEvaluator())->instruction($instructions[$value])->literal);
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
        yield 'receiver state' => [Action::write('cache.value', $value, Expression::literal(new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'receiver'))),['constant','constant','evaluator-state-address','write'],2,1,'cache.value'];
        yield 'reference parameter' => [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [$value], 'output'),['constant','local','write'],1,0,'output'];
        yield 'explicit location' => [Action::assign(LocationRef::parameter('output'), $value),['local','constant','write'],0,1,'output'];
    }

    public function testApplyConnectsAStateAddressToItsReceiver(): void
    {
        $receiver = new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'object-identity');
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'evaluator:write', 0, 8));
        self::assertFalse((new PlanActions($compiler))->apply(Action::write('cache.value', Expression::literal(Term::constant(1)), Expression::literal($receiver))));
        self::assertSame($receiver, (new \Deriver\Deriver\Evaluator\Expression\LiteralEvaluator())->instruction($compiler->instructions[0][0]));
        self::assertSame(['m0'], $compiler->instructions[0][2]->operands);
    }

    #[DataProvider('providerHavocActions')]
    public function testApplyPreservesEveryHavocLocationAndExceptionalPossibility(bool $mayThrow): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'evaluator:havoc', 0, 8));
        self::assertFalse((new PlanActions($compiler))->apply(Action::havoc([LocationRef::parameter('first'),LocationRef::parameter('second')], 'remote write', $mayThrow)));
        self::assertSame(['local','local','evaluator-havoc'], array_column($compiler->instructions[0], 'operation'));
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
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'evaluator:alias', 0, 8));
        self::assertFalse((new PlanActions($compiler))->apply(Action::alias(LocationRef::parameter('destination'), LocationRef::parameter('source'))));
        self::assertSame(['destination','source',''], array_column($compiler->instructions[0], 'name'));
        self::assertSame(['local','local','alias'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame(['m0','m1'], $compiler->instructions[0][2]->operands);
    }

    public function testApplyKeepsBothChoiceCompletionsAndTheContinuationSeparate(): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'evaluator:choice', 0, 8));
        $action = Action::choice(Expression::literal(Term::parameter('flag', 'bool')), [Action::returns(Expression::literal(Term::constant(1)))], [Action::throws(Expression::literal(new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error')))]);
        self::assertFalse((new PlanActions($compiler))->apply($action));
        self::assertSame(3, $compiler->current);
        self::assertSame(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Branch, $compiler->terminators[0]->kind);
        self::assertSame('m0', $compiler->terminators[0]->operand);
        self::assertSame([1,2], $compiler->terminators[0]->targets);
        self::assertSame(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Return, $compiler->terminators[1]->kind);
        self::assertSame('m1', $compiler->terminators[1]->operand);
        self::assertSame(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Throw, $compiler->terminators[2]->kind);
        self::assertSame('m2', $compiler->terminators[2]->operand);
        self::assertSame([], $compiler->instructions[3]);
    }

    #[DataProvider('providerInvocationActions')]
    public function testApplyRoutesEachInvocationThroughPreparedArgumentBinding(Action $action, string $operation): void
    {
        $compiler = new PlanCompiler(new SourceRef('snapshot', 'evaluator:call', 0, 8));
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
