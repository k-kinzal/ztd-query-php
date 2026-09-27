<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Closure\Binding;
use Deriver\Evaluation\Call\Closure\Capture;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Constants;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\IterationStep;
use Deriver\Evaluation\Control\IteratorCursor;
use Deriver\Evaluation\Control\LoopConvergence;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\Effects;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Offset\Protocol;
use Deriver\Evaluation\Offset\ProtocolAccess;
use Deriver\Evaluation\Offset\Reader;
use Deriver\Evaluation\Offset\Strings;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\CompoundAssignment;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyReference;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\LiveArray;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Builtin\TypePredicates;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\Control\LoopLowering;
use Deriver\Source\Compilation\Control\StaticLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(InstructionTransfer::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Binding::class)]
#[UsesClass(Capture::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(IterationStep::class)]
#[UsesClass(IteratorCursor::class)]
#[UsesClass(LoopConvergence::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(Machine::class)]
#[UsesClass(Effects::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Protocol::class)]
#[UsesClass(ProtocolAccess::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Strings::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(CompoundAssignment::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyReference::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(LiveArray::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(TypePredicates::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(LoopLowering::class)]
#[UsesClass(StaticLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
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
        $state->offsets['slot'] = new Address('base', Term::constant(-1));
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
        $access = new ProtocolAccess($receiver, Term::constant('yes'));
        $protocol = new Protocol(new Machine($context));
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

        $state->addresses['unknown'] = new Location('unresolved', unknown: true);
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
        $outer = new Handler(new ExceptionRegion([], null, 99));
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
        $context = SolverFixture::context(configuration:new Configuration(stateSlots:[new StateSlot('example.slot', 'int')]));
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
