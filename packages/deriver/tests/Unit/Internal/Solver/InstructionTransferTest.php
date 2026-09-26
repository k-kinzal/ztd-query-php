<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\InstructionTransfer;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(InstructionTransfer::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\StaticLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\LiveArray::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Closure\Binding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Closure\Capture::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\IterationStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\IteratorCursor::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\Effects::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Protocol::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\ProtocolAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Strings::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\TypePredicates::class)]
#[UsesClass(Term::class)]
#[Small]
final class InstructionTransferTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target() { $x="before"; $x="after"; return $x . ":done"; }');
        self::assertSame('after:done', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testOtherConvertsEvalIntoAnExplicitStateBoundary(): void
    {
        $context = SolverFixture::context();
        $machine = new Machine($context);
        $body = $context->program->callable('target');
        self::assertNotNull($body);

        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $value = (new InstructionTransfer($machine))->other($body, new Instruction('eval', 'symbol-table-boundary', $body->source, 'result', name:'UNSUPPORTED_LANGUAGE_FEATURE'), $state);
        self::assertSame('opaque', $value->kind);
        self::assertSame('opaque', $state->snapshot()['x']->kind);
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testBoundaryKeepsBothCaughtAndNormallyCompletedUnknownCode(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target($code){try{eval($code);}catch(Throwable $e){return "caught";}return "normal";}');
        $values = array_column(array_column(array_column($result->normalOutcomes, 'values'), 'return'), 'literal');
        sort($values);
        self::assertSame(['caught','normal'], $values);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertNotEmpty($result->frontiers);
    }
    public function testStorageRoutesOffsetReadsThroughContainerSemantics(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $state->addresses['base'] = $state->memory->allocate(Term::constant('abc'));
        $state->offsets['slot'] = new \Deriver\Internal\Solver\Offset\Address('base', Term::constant(-1));
        $read = new Instruction('read', 'read', $body->source, 'result', ['slot']);
        $paths = (new InstructionTransfer(new Machine($context)))->storage($body, $read, $state);
        self::assertNotNull($paths);
        self::assertSame('c', $paths[0]->value('result')->native());
    }
    public function testOffsetReadPreservesSilentAbsenceOnComputedArrays(): void
    {
        $context = SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->memory->cells['box'] = Term::array(['value' => Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new Machine($context));
        $state->registers['array'] = Term::array([]);
        $state->registers['key'] = Term::constant('absent');
        $instruction = new Instruction('read', 'array-read', $body->source, 'result', ['array','key'], attributes: ['silent' => true]);
        $paths = (new InstructionTransfer(new Machine($context)))->offsetRead($body, $instruction, $state);
        self::assertSame('uninitialized', $paths[0]->value('result')->kind);
        self::assertSame([], $context->frontiers);
    }
    public function testStorageRetainsUnknownReferenceEffectsAndExceptions(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Machine($context);
        $state = new State();
        $instruction = new Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $state->addresses['unknown'] = new \Deriver\Internal\Memory\Location('unresolved', unknown: true);
        $state->memory->write($state->local('value'), Term::constant(1));
        $reference = new Instruction('ref', 'reference', $body->source, 'result', ['unknown']);
        $paths = (new InstructionTransfer($machine))->storage($body, $reference, $state);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('opaque', $paths[0]->registers['result']->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
    }
    public function testPreparationLeavesOrdinaryOperationsToValueTransfer(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new Instruction('value', 'constant', $body->source, constant: Term::constant(1));
        self::assertNull((new InstructionTransfer(new Machine($context)))->preparation($body, $instruction, new State()));
    }

    /**
     * @param Term $item Unpack operand
     */
    #[DataProvider('providerInvalidUnpacks')]
    public function testUnpackRejectsNonIterableValues(Term $item, string $exception): void
    {
        $state = new State();
        $state->registers = ['array' => Term::array([]),'item' => $item];
        $instruction = new Instruction('unpack', 'array-unpack', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['array','','item']);
        $paths = (new InstructionTransfer(new Machine(SolverFixture::context())))->unpack($instruction, $state);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertNotNull($paths[0]->completion->value);
        self::assertSame($exception, $paths[0]->completion->value->literal);
    }

    /**
     * @return array<string,array{Term,string}>
     */
    public static function providerInvalidUnpacks(): array
    {
        return ['null' => [Term::constant(null),'Error'],'int' => [Term::constant(3),'Error'],'string' => [Term::constant('abc'),'Error'],'bool' => [Term::constant(false),'Error'],'closure' => [new Term('closure', 'body'),'TypeError']];
    }

    public function testUnpackRetainsUnknownIterableEffectsAndBothExits(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->memory->cells['global:x'] = Term::constant(4);
        $state->registers = ['array' => Term::array([]),'item' => Term::parameter('input', 'iterable')];
        $instruction = new Instruction('unpack', 'array-unpack', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['array','','item']);
        $paths = (new InstructionTransfer(new Machine($context)))->unpack($instruction, $state);
        self::assertCount(2, $paths);
        self::assertSame('opaque', $paths[0]->registers['result']->kind);
        self::assertSame('opaque', $paths[0]->memory->cells['global:x']->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column(array_values($context->frontiers), 'code'));
    }

    public function testUnpackMergesClosedArraysInInsertionOrder(): void
    {
        $state = new State();
        $state->registers = ['array' => Term::fromNative(['a' => 1,0 => 'first']),'item' => Term::fromNative(['a' => 2,7 => 'last'])];
        $instruction = new Instruction('unpack', 'array-unpack', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['array','','item']);
        $paths = (new InstructionTransfer(new Machine(SolverFixture::context())))->unpack($instruction, $state);
        self::assertCount(1, $paths);
        self::assertSame(['a' => 2,0 => 'first',1 => 'last'], $paths[0]->registers['result']->native());
        self::assertSame('normal', $paths[0]->completion->kind);
    }


    /**
     * @param string $source Captured PHP source
     * @param mixed $expected Concrete returned state
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('providerInstructionPrograms')]
    public function testApplyDispatchesEffectsAndReturnsTheFinalState(string $source, mixed $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function providerInstructionPrograms(): iterable
    {
        yield 'array explicit and append' => ['<?php function target(){$a=[4=>"a"]; $a["key"]="b"; $a[]="c";return $a;}', [4 => 'a','key' => 'b',5 => 'c']];
        yield 'array unpack' => ['<?php function target(){return [1,...[9=>2,"x"=>3],4];}',[1,2,'x' => 3,4]];
        yield 'computed offset' => ['<?php function target(){return (["x"=>12])["x"];} ',12];
        yield 'unary conversion' => ['<?php function target(){return [(int)"17",(bool)[],!1,-4];}',[17,false,false,-4]];
        yield 'string conversion method' => ['<?php class Text{function __toString(){return "value";}} function target(){return (string)new Text;}', 'value'];
        yield 'compound assignment' => ['<?php function target(){$a=["x"=>4];$v=($a["x"]+=3);return [$a,$v];}',[['x' => 7],7]];
        yield 'constant fetch' => ['<?php const X=12;function target(){return X;}',12];
        yield 'class constant' => ['<?php class A{const X=13;}function target(){return A::X;}',13];
        yield 'instanceof' => ['<?php class A{}function target(){return (new A) instanceof A;}',true];
        yield 'static call' => ['<?php class A{static function value($x){return $x+1;}}function target(){return A::value(4);}',5];
        yield 'method call' => ['<?php class A{public $x=4;function value(){return $this->x;}}function target(){return (new A)->value();}',4];
        yield 'clone' => ['<?php class A{public $x=4;}function target(){$a=new A;$b=clone $a;$b->x=7;return [$a->x,$b->x];}',[4,7]];
        yield 'closure' => ['<?php function target(){$x=4;$f=function($v)use($x){return $x+$v;};return $f(3);}',7];
        yield 'first class function' => ['<?php function value($v){return $v+2;}function target(){$f=value(...);return $f(3);}',5];
        yield 'first class method' => ['<?php class A{function value($v){return $v+2;}}function target(){$a=new A;$f=$a->value(...);return $f(3);}',5];
        yield 'reference array source' => ['<?php function target(){$a=["x"=>1];$r=&$a["x"];$r=7;return $a;}', ['x' => 7]];
        yield 'reference property source' => ['<?php class A{public int $x=1;}function target(){$a=new A;$r=&$a->x;$r=7;return $a->x;}',7];
        yield 'property write' => ['<?php class A{public int $x=1;}function target(){$a=new A;$a->x=4;return $a->x;}',4];
        yield 'property increment' => ['<?php class A{public int $x=1;}function target(){$a=new A;$v=$a->x++;return [$v,$a->x];}',[1,2]];
        yield 'property unset' => ['<?php class A{public int $x=1;}function target(){$a=new A;unset($a->x);return isset($a->x);}',false];
        yield 'typed reference assignment' => ['<?php class A{public int $x=1;}function target(){$a=new A;$r=&$a->x;$r="12";return [$r,$a->x];}',[12,12]];
        yield 'typed reference error preserves state' => ['<?php class A{public int $x=1;}function target(){$a=new A;$r=&$a->x;try{$r=[];}catch(TypeError $e){}return [$r,$a->x];}',[1,1]];
        yield 'array offset increment' => ['<?php function target(){$a=[4];$v=$a[0]++;return [$v,$a];}',[4,[5]]];
        yield 'array offset unset' => ['<?php function target(){$a=["x"=>4,"y"=>5];unset($a["x"]);return $a;}', ['y' => 5]];
        yield 'by reference loop' => ['<?php function target(){$a=[1,2];foreach($a as &$v){$v+=2;}unset($v);return $a;}',[3,4]];
        yield 'by value loop' => ['<?php function target(){$a=[];foreach(["x"=>3,"y"=>4] as $k=>$v){$a[$k]=$v+1;}return $a;}', ['x' => 4,'y' => 5]];
        yield 'static local' => ['<?php function counter(){static $x=0;return ++$x;}function target(){return [counter(),counter()];}',[1,2]];
        yield 'global alias' => ['<?php function target(){global $x;$x=4;$r=&$x;$r=8;return $x;}',8];
        yield 'throw instruction' => ['<?php function target(){try{throw new RuntimeException;}catch(RuntimeException $e){return 9;}}',9];
        yield 'ArrayAccess computed read' => ['<?php class Bag implements ArrayAccess{function offsetGet($k):mixed{return $k."!";}function offsetExists($k):bool{return true;}function offsetSet($k,$v):void{}function offsetUnset($k):void{}}function target(){return (new Bag)["x"];}','x!'];
        yield 'ArrayAccess silent read' => ['<?php class Bag implements ArrayAccess{function offsetExists($k):bool{return false;}function offsetGet($k):mixed{throw new Error;}function offsetSet($k,$v):void{}function offsetUnset($k):void{}}function target(){return (new Bag)["x"]??8;}',8];
    }

    public function testApplyArrayConstructionPreservesSharedReferenceCells(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $cell = $state->memory->allocate(Term::constant(4));
        $reference = new Term('cell', $cell->root);
        $state->registers = ['array' => Term::array([]), 'key' => Term::constant('shared'), 'value' => $reference];
        $paths = (new InstructionTransfer(new Machine($context)))->apply($body, new Instruction('set', 'array-set', $body->source, 'result', ['array','key','value']), $state);
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame($reference, $paths[0]->registers['result']->operands['shared']);
        $paths[0]->memory->write($cell, Term::constant(9));
        self::assertSame(9, $paths[0]->memory->element($paths[0]->registers['result'], 'shared')->native());
    }

    public function testApplyArrayConstructionRetainsAnUncomputedElement(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers['array'] = Term::array([]);
        $paths = (new InstructionTransfer(new Machine($context)))->apply($body, new Instruction('set', 'array-set', $body->source, 'result', ['array','','absent']), $state);
        self::assertCount(1, $paths);
        self::assertSame('UNCOMPUTED_REGISTER', $paths[0]->registers['result']->operands[0]->literal);
        self::assertSame('normal', $paths[0]->completion->kind);
    }

    public function testUnpackOpenArraysKeepsBothContinuationsAndKnownDependencies(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $array = Term::array(['known' => Term::constant(4)]);
        $item = Term::array(['secret' => Term::constant(8, true)], open:true);
        $state->registers = ['array' => $array,'item' => $item];
        $instruction = new Instruction('unpack', 'array-unpack', new SourceRef('test', 'fixture.php', 0, 1), 'result', ['array','','item']);
        $paths = (new InstructionTransfer(new Machine($context)))->unpack($instruction, $state);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('Error', $paths[1]->completion->value?->literal);
        self::assertSame('array-merge', $paths[0]->registers['result']->kind);
        self::assertTrue($paths[0]->registers['result']->isSecret());
        $frontier = array_values($context->frontiers)[0];
        self::assertSame('WIDENED', $frontier->code);
        self::assertSame('symbolic-unpack-append', $frontier->operation);
        self::assertSame([$array,$item], $frontier->residual?->operands);
    }

    /**
     * @param string $operation Boundary instruction
     * @param string $name Descriptive or diagnostic name
     * @param string $reason Expected frontier
     */
    #[DataProvider('providerBoundaries')]
    public function testBoundaryInvalidatesStateAndKeepsIndependentExitStates(string $operation, string $name, string $reason): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->memory->write($state->local('value'), Term::constant(7));
        $instruction = new Instruction('boundary', $operation, $body->source, 'result', name:$name);
        $paths = (new InstructionTransfer(new Machine($context)))->apply($body, $instruction, $state);
        self::assertCount(2, $paths);
        self::assertNotSame($paths[0], $paths[1]);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('Throwable', $paths[1]->completion->value?->literal);
        self::assertSame(true, $paths[1]->completion->value->attributes['uncertain']);
        self::assertSame($reason, $paths[0]->registers['result']->literal);
        self::assertSame($reason, $paths[0]->snapshot()['value']->literal);
        self::assertSame([$reason], array_column($context->frontiers, 'code'));
        self::assertSame([$name], array_column($context->frontiers, 'operation'));
        $paths[0]->memory->write($paths[0]->local('value'), Term::constant(9));
        self::assertSame($reason, $paths[1]->snapshot()['value']->literal);
    }

    /**
     * @return iterable<string,array{string,string,string}>
     */
    public static function providerBoundaries(): iterable
    {
        yield 'unsupported' => ['unsupported','unknown-syntax','UNSUPPORTED_LANGUAGE_FEATURE'];
        yield 'symbol table' => ['symbol-table-boundary','DYNAMIC_SYMBOL_TABLE','DYNAMIC_SYMBOL_TABLE'];
        yield 'order' => ['uncertain-order','multiple-effects','UNSPECIFIED_EVALUATION_ORDER'];
    }

    public function testOtherPushesTheSelectedExceptionRegionOntoExistingHandlers(): void
    {
        $context = SolverFixture::context('<?php function target(){try{return 1;}finally{$x=2;}}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $outer = new \Deriver\Internal\Solver\Control\Handler(new \Deriver\Internal\IR\ExceptionRegion([], null, 99));
        $state->handlers = [$outer];
        $state = $state->fork();
        $result = (new InstructionTransfer(new Machine($context)))->other($body, new Instruction('enter', 'enter-try', $body->source, attributes:['region' => 0]), $state);
        self::assertNull($result->native());
        self::assertCount(2, $state->handlers);
        $handlers = $state->handlers;
        self::assertSame($outer, $handlers[0]);
        self::assertSame($body->regions[0], $handlers[1]->region);
        self::assertSame('try', $handlers[1]->phase);
    }

    public function testOtherThrowRetainsTheEvaluatedThrowableIdentity(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $value = new Term('object', 'exception', attributes:['class' => 'RuntimeException']);
        $state->registers['exception'] = $value;
        $result = (new InstructionTransfer(new Machine($context)))->other($body, new Instruction('throw', 'throw', $body->source, 'result', ['exception']), $state);
        self::assertSame($value, $result);
        self::assertSame($value, $state->completion->value);
        self::assertSame('throw', $state->completion->kind);
    }

    public function testPreparationResolvesRegisteredModelStateStorage(): void
    {
        $context = SolverFixture::context(configuration:new \Deriver\Api\Project\Configuration(stateSlots:[new \Deriver\Model\State\StateSlot('example.slot', 'int')]));
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers['receiver'] = new Term('object', 'external');
        $paths = (new InstructionTransfer(new Machine($context)))->preparation($body, new Instruction('slot', 'model-state-address', $body->source, 'result', ['receiver'], 'example.slot'), $state);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame('location', $paths[0]->registers['result']->kind);
        self::assertSame('model:external', $paths[0]->addresses['result']->root);
        self::assertSame(['example.slot'], $paths[0]->addresses['result']->path);
        self::assertSame('state-input', $paths[0]->memory->read($paths[0]->addresses['result'])->kind);
        self::assertSame([], $paths[0]->properties);
    }

    public function testPreparationAppliesExplicitModelWriteFootprintsBeforeContinuing(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->addresses['written'] = $state->memory->allocate(Term::constant(7));
        $state->memory->write($state->local('unrelated'), Term::constant(9));
        $paths = (new InstructionTransfer(new Machine($context)))->preparation($body, new Instruction('havoc', 'model-havoc', $body->source, 'result', ['written'], 'external-write'), $state);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $paths[0]->registers['result']->literal);
        self::assertSame('opaque', $paths[0]->memory->read($paths[0]->addresses['written'])->kind);
        self::assertSame(9, $paths[0]->snapshot()['unrelated']->native());
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
    }
}
