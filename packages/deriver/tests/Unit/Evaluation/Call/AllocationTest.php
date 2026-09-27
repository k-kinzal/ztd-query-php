<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
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
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
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
use Deriver\Evaluation\Call\ProviderDispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
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
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Offset\Reader;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyReference;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchRequest;
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
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
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
use Deriver\Source\Declaration\Traits\Members;
use Deriver\Source\Declaration\Traits\PropertyScope;
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
use Deriver\Value\Lattice;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;
use Tests\Fake\SummaryFixture;

#[CoversClass(Allocation::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
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
#[UsesClass(ProviderDispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
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
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyReference::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(DispatchRequest::class)]
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
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
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
#[UsesClass(Members::class)]
#[UsesClass(PropertyScope::class)]
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
#[UsesClass(Lattice::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class AllocationTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{public $child; public $value=0;}function target(){$a=new Box;$a->child=new Box;$b=clone $a;$b->child->value=3;return $a->child->value;}');
        self::assertSame(3, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInitializeEvaluatesEachAllocationDefaultIndependently(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public $x=[1];}function target(){$a=new B;$b=new B;$a->x[0]=2;return [$a->x,$b->x];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([[2],[1]], $result->normalOutcomes[0]->values['return']->native());
    }
    public function testRecordCreatesDistinctAbstractStatePerObject(): void
    {
        $context = SolverFixture::context(configuration: new Configuration(stateSlots: [new StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        $storage = new StateStorage($context);

        $instruction = new Instruction('new', 'new', new SourceRef('test', 'fixture.php', 0, 1));
        $allocation = new Allocation(new Machine($context));
        $a = $allocation->record($instruction, $state, 'Box', Term::constant('Box'), false);
        $b = $allocation->record($instruction, $state, 'Box', Term::constant('Box'), false);
        self::assertNotSame($a->literal, $b->literal);
        self::assertSame(1, $state->memory->read(new Location('model:' . $a->literal, ['example.slot']))->native());
    }

    public function testRecordClonesHeapAndModelStateWithTheirDeclaredSharingRules(): void
    {
        $context = SolverFixture::context(configuration:new Configuration(stateSlots:[new StateSlot('example.copy', 'int', Term::constant(1)),new StateSlot('example.reset', 'int', Term::constant(2), 'reset')]));
        $body = SummaryFixture::body($context);
        $state = new State();
        $source = new Term('object', 'original', attributes:['class' => 'Box']);
        $shared = $state->memory->allocate(Term::constant(3));
        $reference = new Term('cell', $shared->root);
        $child = new Term('object', 'nested', attributes:['class' => 'Child']);
        $state->memory->cells['object:original'] = Term::array(['value' => Term::constant(4),'shared' => $reference,'child' => $child]);
        $state->memory->propertyTypes['object:original'] = ['value' => 'int','shared' => 'int','child' => 'Child'];
        $state->memory->cells['model:original'] = Term::array(['example.copy' => Term::constant(8),'example.reset' => Term::constant(9)]);
        $object = (new Allocation(new Machine($context)))->record(new Instruction('site', 'clone', $body->source), $state, 'Box', $source, true);
        self::assertNotSame('original', $object->literal);
        self::assertSame('Box', $object->attributes['class']);
        self::assertSame('Box', $state->memory->classes[(string)$object->literal]);
        $root = 'object:'.$object->literal;
        self::assertSame($reference, $state->memory->cells[$root]->operands['shared']);
        self::assertSame($child, $state->memory->cells[$root]->operands['child']);
        self::assertSame(['value' => 'int','shared' => 'int','child' => 'Child'], $state->memory->propertyTypes[$root]);
        self::assertSame(['value' => true,'shared' => true,'child' => true], $state->memory->cloneWrites[$root]);
        self::assertSame(8, $state->memory->read(new Location('model:'.$object->literal, ['example.copy']))->native());
        self::assertSame(2, $state->memory->read(new Location('model:'.$object->literal, ['example.reset']))->native());
        $state->memory->write(new Location($root, ['value']), Term::constant(10));
        $state->memory->write(new Location($root, ['shared']), Term::constant(11));
        self::assertSame(4, $state->memory->read(new Location('object:original', ['value']))->native());
        self::assertSame(11, $state->memory->read(new Location('object:original', ['shared']))->native());
    }

    public function testRecordUnknownClonesRetainOpenPropertyStorage(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $object = (new Allocation(new Machine($context)))->record(new Instruction('site', 'clone', $body->source), $state, 'External', new Term('object', 'external'), true);
        $root = 'object:'.$object->literal;
        self::assertSame('array', $state->memory->cells[$root]->kind);
        self::assertTrue($state->memory->cells[$root]->attributes['open']);
        self::assertSame([], $state->memory->propertyTypes[$root]);
        self::assertSame([], $state->memory->cloneWrites[$root]);
    }

    public function testRecordFreshObjectsStartWithClosedStorageAndNoClonePermissions(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->memory->cells['object:Box'] = Term::fromNative(['old' => 9]);
        $object = (new Allocation(new Machine($context)))->record(new Instruction('site', 'new', $body->source), $state, 'Box', Term::constant('Box'), false);
        $root = 'object:'.$object->literal;
        self::assertSame([], $state->memory->cells[$root]->native());
        self::assertSame([], $state->memory->cloneWrites);
        self::assertSame('Box', $state->memory->classes[(string)$object->literal]);
    }

    public function testInitializeKeepsParentPrivateSlotsAndSkipsStaticProperties(): void
    {
        $context = SolverFixture::context('<?php class ParentBox{private int $value=3;protected int $inherited=4;public static int $global=99;}class Box extends ParentBox{private int $value=7;public $plain;public string $typed;}function target(){}');
        $state = new State();
        $object = new Term('object', 'box', attributes:['class' => 'Box']);
        $state->memory->cells['object:box'] = Term::array([]);
        $paths = (new Allocation(new Machine($context)))->initialize('Box', $object, $state);
        self::assertCount(1, $paths);
        $memory = $paths[0]->memory;
        self::assertSame(3, $memory->read(new Location('object:box', ['ParentBox::value']))->native());
        self::assertSame(7, $memory->read(new Location('object:box', ['Box::value']))->native());
        self::assertSame(4, $memory->read(new Location('object:box', ['inherited']))->native());
        self::assertNull($memory->read(new Location('object:box', ['plain']))->native());
        self::assertSame('uninitialized', $memory->read(new Location('object:box', ['typed']))->kind);
        self::assertSame('string', $memory->read(new Location('object:box', ['typed']))->attributes['type']);
        self::assertArrayNotHasKey('global', $memory->cells['object:box']->operands);
        self::assertSame(['ParentBox::value' => 'int','inherited' => 'int','Box::value' => 'int','plain' => 'mixed','typed' => 'string'], $memory->propertyTypes['object:box']);
    }

    public function testInitializeResolvesRelativePropertyTypesInTheDeclaringClass(): void
    {
        $context = SolverFixture::context('<?php class Ancestor{}class ParentBox extends Ancestor{public self $self;public parent $parent;}class Box extends ParentBox{}function target(){}');
        $state = new State();
        $object = new Term('object', 'box', attributes:['class' => 'Box']);
        $state->memory->cells['object:box'] = Term::array([]);
        $paths = (new Allocation(new Machine($context)))->initialize('Box', $object, $state);
        self::assertSame(['self' => 'ParentBox','parent' => 'Ancestor'], $paths[0]->memory->propertyTypes['object:box']);
    }

    public function testInitializeCycleGuardsDoNotOverwriteExistingState(): void
    {
        $context = SolverFixture::context('<?php class Box{public $value=9;}function target(){}');
        $state = new State();
        $object = new Term('object', 'box', attributes:['class' => 'Box']);
        $state->memory->cells['object:box'] = Term::fromNative(['value' => 4]);
        $paths = (new Allocation(new Machine($context)))->initialize('Box', $object, $state, ['Box']);
        self::assertSame([$state], $paths);
        self::assertSame(['value' => 4], $state->memory->cells['object:box']->native());
        self::assertSame([], $state->memory->propertyTypes);
    }

    public function testInitializeNativeParentsBeforeSourcePropertyDefaults(): void
    {
        $context = SolverFixture::context('<?php class Problem extends RuntimeException{public $tag="source";}function target(){}');
        $state = new State();
        $object = new Term('object', 'problem', attributes:['class' => 'Problem']);
        $state->memory->cells['object:problem'] = Term::array([]);
        $paths = (new Allocation(new Machine($context)))->initialize('Problem', $object, $state);
        self::assertCount(1, $paths);
        self::assertSame('source', $paths[0]->memory->read(new Location('object:problem', ['tag']))->native());
        self::assertSame('', $paths[0]->memory->read(new Location('object:problem', ['message']))->native());
        self::assertSame(0, $paths[0]->memory->read(new Location('object:problem', ['code']))->native());
        self::assertNull($paths[0]->memory->read(new Location('object:problem', ['Exception::previous']))->native());
    }

    /**
     * @param string $source Captured source
     * @param mixed $expected Returned observable state
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[DataProvider('providerAllocationPrograms')]
    public function testApplyHonorsInitializationAndLifecycleEffects(string $source, mixed $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @return iterable<string,array{string,mixed}>
     */
    public static function providerAllocationPrograms(): iterable
    {
        yield 'constructor arguments' => ['<?php class Box{public $value=1;function __construct($v){$this->value=$v;}}function target(){return (new Box(7))->value;}',7];
        yield 'inherited constructor' => ['<?php class ParentBox{public $value;function __construct($v){$this->value=$v;}}class Box extends ParentBox{}function target(){return (new Box(8))->value;}',8];
        yield 'constructor failure' => ['<?php class Box{function __construct(){throw new RuntimeException;}}function target(){try{new Box;return 1;}catch(RuntimeException $e){return 2;}}',2];
        yield 'initializer failure before constructor' => ['<?php class Box{public $value=1/0;function __construct(){throw new RuntimeException;}}function target(){try{new Box;return 1;}catch(Error $e){return 2;}}',2];
        yield 'clone hook' => ['<?php class Box{public $value=1;function __clone(){$this->value=7;}}function target(){$a=new Box;$a->value=4;$b=clone $a;return [$a->value,$b->value];}',[4,7]];
        yield 'clone failure' => ['<?php class Box{function __clone(){throw new RuntimeException;}}function target(){$a=new Box;try{$b=clone $a;return 1;}catch(RuntimeException $e){return 2;}}',2];
        yield 'clone does not call constructor' => ['<?php class Box{public static $calls=0;function __construct(){self::$calls++;}}function target(){$a=new Box;$b=clone $a;return Box::$calls;}',1];
        yield 'readonly clone reinitialization' => ['<?php class Box{function __construct(public readonly int $value){}function __clone(){$this->value=7;}}function target(){$a=new Box(4);$b=clone $a;return [$a->value,$b->value];}',[4,7]];
        yield 'readonly permission ends after clone' => ['<?php class Box{function __construct(public readonly int $value){}function __clone(){$this->value=7;}}function target(){$a=new Box(4);$b=clone $a;try{$b->value=8;}catch(Error $e){}return $b->value;}',7];
        yield 'private property scope' => ['<?php class A{private $x=1;function a(){return $this->x;}}class B extends A{private $x=2;function b(){return $this->x;}}function target(){$b=new B;return [$b->a(),$b->b()];}',[1,2]];
        yield 'trait property' => ['<?php trait Values{public $value=4;}class Box{use Values;}function target(){return (new Box)->value;}',4];
        yield 'native constructor' => ['<?php function target(){$e=new RuntimeException("message",7);return [$e->getMessage(),$e->getCode()];}',['message',7]];
        yield 'abstract allocation fails' => ['<?php abstract class Box{}function target(){try{new Box;return 1;}catch(Error $e){return 2;}}',2];
        yield 'private constructor fails' => ['<?php class Box{private function __construct(){}}function target(){try{new Box;return 1;}catch(Error $e){return 2;}}',2];
        yield 'enum allocation fails' => ['<?php enum Box{case One;}function target(){try{new Box;return 1;}catch(Error $e){return 2;}}',2];
        yield 'clone scalar fails' => ['<?php function target(){$x=7;try{$a=clone $x;return 1;}catch(Error $e){return 2;}}',2];
        yield 'constructorless positional arguments' => ['<?php class Box{public $x=7;}function target(){return (new Box(1,2))->x;}',7];
        yield 'constructorless named argument fails' => ['<?php class Box{}function target(){try{new Box(value:7);return 1;}catch(Error $e){return 2;}}',2];
    }

    public function testApplyDestructorLifetimeSealsInclusiveOutcomesBeforeInventingObjectState(): void
    {
        $context = SolverFixture::context('<?php class Box{function __destruct(){}}function target(){}');
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->registers['class'] = Term::constant('Box');
        $paths = (new Allocation(new Machine($context)))->apply($body, new Instruction('new', 'new', $body->source, 'result', ['class']), $state, []);
        self::assertCount(2, $paths);
        self::assertSame(['return','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $paths[0]->completion->value?->literal);
        self::assertSame('Throwable', $paths[1]->completion->value?->literal);
        self::assertSame('opaque', $paths[0]->registers['result']->kind);
        self::assertContains('destructor-lifetime', array_column($context->frontiers, 'operation'));
        self::assertSame([], $paths[0]->memory->classes);
    }
}
