<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

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
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
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
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyMagic;
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
use Deriver\Model\Builtin\Library;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
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
use Deriver\Result\Exceptional;
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
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Transfer\PropertyTransfer
 */
#[CoversClass(PropertyTransfer::class)]
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
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
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
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyMagic::class)]
#[UsesClass(PropertyReference::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Library::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(Exceptional::class)]
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
final class PropertyTransferTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyHandlesInaccessibleSilentReads(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{private $x=1;}function target(){$b=new B;return [isset($b->x),$b->x??9,empty($b->x)];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([false, 9, true], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testWriteEnforcesReadonlyClasses(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php readonly class B{function __construct(public int $x){}}function target(){$b=new B(1);try{$b->x=2;}catch(Error $e){return $b->x;}return 999;}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInitializeEvaluatesInheritedStaticDefaults(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class A{public static int $x=1+2;}class B extends A{}function target(){return B::$x;}');
        self::assertSame(3, $result->normalOutcomes[0]->values['return']->native());
    }
    public function testErrorRetainsTheUnchangedMemory(): void
    {
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $result = (new PropertyTransfer(new Machine(\Tests\Fake\SolverFixture::context())))->error($state, 'TypeError');
        self::assertSame('throw', $result->completion->kind);
        self::assertSame('TypeError', $result->completion->value?->literal);
        self::assertSame(1, $result->snapshot()['x']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testTransferChecksTheDeclaredTypeBeforeCommittingAWrite(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php declare(strict_types=1);class B{public int $x=1;}function target(){$b=new B;try{$b->x="42";}catch(TypeError $e){return $b->x;}return 999;}');
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testWriteChecksEveryPropertySharingTheDestinationCell(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $x=1;public int|string $y=1;}function target(){$b=new B;$b->y=&$b->x;try{$b->y="word";}catch(TypeError $e){return [$b->x,$b->y];}return 999;}');
        self::assertSame([1,1], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUncertainRetainsErrorsAfterUnknownCallsCanUnsetTypedProperties(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $x=2;} function target(){$b=new B; $alias=$b; unknown($b); $after=5; try{return $alias->x;}catch(Error $e){return $after;}}');
        self::assertContains(5, array_map(static fn ($outcome) => $outcome->values['return']->literal, $result->normalOutcomes));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPropertyOperationContracts')]
    public function testApplyPreservesPropertyValuesAndErrors(string $source, mixed $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return iterable<string, array{string,mixed}>
     */
    public static function providerPropertyOperationContracts(): iterable
    {
        yield 'typed uninitialized read' => ['<?php class B{public int $x;}function target(){$b=new B;try{return $b->x;}catch(Error $e){return "uninitialized";}}', 'uninitialized'];
        yield 'nullable uninitialized silent' => ['<?php class B{public ?int $x;}function target(){$b=new B;return [isset($b->x),$b->x??8];}', [false, 8]];
        yield 'typed reference rejects later write' => ['<?php class B{public int $x=1;}function target(){$b=new B;$r=&$b->x;try{$r="word";}catch(TypeError $e){return [$b->x,$r];}return 999;}', [1, 1]];
        yield 'nullable reference initializes' => ['<?php class B{public ?int $x;}function target(){$b=new B;$r=&$b->x;return [$b->x,$r];}', [null, null]];
        yield 'nonnullable reference fails' => ['<?php class B{public int $x;}function target(){$b=new B;try{$r=&$b->x;}catch(Error $e){return isset($b->x);}return 999;}', false];
        yield 'alias coerces source' => ['<?php class B{public int $x=1;}function target(){$b=new B;$r="42";$b->x=&$r;return [$b->x,$r];}', [42, 42]];
        yield 'increment typed property' => ['<?php class B{public int $x=1;}function target(){$b=new B;return [$b->x++,$b->x,++$b->x];}', [1, 2, 3]];
        yield 'increment typed uninitialized' => ['<?php class B{public int $x;}function target(){$b=new B;try{$b->x++;}catch(Error $e){return isset($b->x);}return 999;}', false];
        yield 'readonly unset inside before initialization' => ['<?php class B{public readonly int $x;function run(){unset($this->x);$this->x=4;return $this->x;}}function target(){return (new B)->run();}', 4];
        yield 'readonly unset outside before initialization' => ['<?php class B{public readonly int $x;}function target(){$b=new B;try{unset($b->x);}catch(Error $e){return "blocked";}return "allowed";}', 'blocked'];
        yield 'readonly unset after initialization' => ['<?php class B{public readonly int $x;function __construct(){$this->x=4;}function run(){try{unset($this->x);}catch(Error $e){return $this->x;}return 999;}}function target(){return (new B)->run();}', 4];
        yield 'readonly initialize outside' => ['<?php class B{public readonly int $x;}function target(){$b=new B;try{$b->x=4;}catch(Error $e){return isset($b->x);}return 999;}', false];
        yield 'readonly clone writes once' => ['<?php class B{function __construct(public readonly int $x){}function __clone(){$this->x=2;try{$this->x=3;}catch(Error $e){}}}function target(){$a=new B(1);$b=clone $a;return [$a->x,$b->x];}', [1, 2]];
        yield 'readonly failed clone type retains opportunity' => ['<?php class B{function __construct(public readonly int $x){}function __clone(){try{$this->x=[];}catch(TypeError $e){}$this->x=3;}}function target(){$a=new B(1);$b=clone $a;return [$a->x,$b->x];}', [1, 3]];
        yield 'static array default' => ['<?php class B{public static array $a=[1];}function target(){B::$a[]=2;return B::$a;}', [1, 2]];
        yield 'static multiple defaults' => ['<?php class B{public static int $x=1;public static int $y=2;}function target(){B::$x=9;return [B::$x,B::$y,B::$x];}', [9, 2, 9]];
        yield 'static null default' => ['<?php class B{public static $x;}function target(){return B::$x;}', null];
        yield 'static typed no default' => ['<?php class B{public static int $x;}function target(){try{return B::$x;}catch(Error $e){return "uninitialized";}}', 'uninitialized'];
        yield 'static default throws' => ['<?php class B{public static int $x=1/0;}function target(){try{return B::$x;}catch(DivisionByZeroError $e){return "division";}}', 'division'];
        yield 'static missing property' => ['<?php class B{}function target(){try{return B::$missing;}catch(Error $e){return "missing";}}', 'missing'];
        yield 'instance property as static' => ['<?php class B{public int $x=1;}function target(){try{return B::$x;}catch(Error $e){return "instance";}}', 'instance'];
        yield 'inaccessible public read' => ['<?php class B{private int $x=1;}function target(){$b=new B;try{return $b->x;}catch(Error $e){return "private";}}', 'private'];
        yield 'visible silent read' => ['<?php class B{public int $x=3;}function target(){$b=new B;return [isset($b->x),$b->x??8];}', [true, 3]];
        yield 'magic private read' => ['<?php class B{private int $x=1;function __get($name){return "magic:".$name;}}function target(){return (new B)->x;}', 'magic:x'];
        yield 'magic missing read' => ['<?php class B{function __get($name){return "magic:".$name;}}function target(){return (new B)->x;}', 'magic:x'];
        yield 'magic not used for visible property' => ['<?php class B{public int $x=1;function __get($name){return 99;}}function target(){return (new B)->x;}', 1];
        yield 'magic inaccessible write' => ['<?php class B{private int $x=1;public int $seen=0;function __set($name,$value){$this->seen=$value;}}function target(){$b=new B;$b->x=7;return $b->seen;}', 7];
        yield 'readonly indirect array write' => ['<?php class B{function __construct(public readonly array $x){}}function target(){$b=new B([1]);try{$b->x[]=2;}catch(Error $e){return $b->x;}return 999;}', [1]];
        yield 'readonly object handle mutation' => ['<?php class C{public int $x=1;}class B{function __construct(public readonly C $value){}}function target(){$b=new B(new C);$b->value->x=2;return $b->value->x;}', 2];
        yield 'scalar property write fails' => ['<?php function target(){$a=1;try{$a->x=2;}catch(Error $e){return $a;}return 999;}', 1];
        yield 'typed unset then initialize' => ['<?php class B{public int $x=1;}function target(){$b=new B;unset($b->x);$b->x=2;return $b->x;}', 2];
    }

    public function testWritePreservesBothTypeOutcomesAndTheirIndependentMemory(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source, strict:true);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $before = Term::constant(1);
        $state->memory->write($address, $before);
        $argument = Term::parameter('input');
        $state->registers['value'] = $argument;
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', 'int'));
        $instruction = new Instruction('i', 'write', $source, 'result', ['address','value']);
        $paths = (new PropertyTransfer(new Machine($context)))->write($caller, $instruction, $state, $slot, $address);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('type-refinement', $paths[0]->memory->read($address)->kind);
        self::assertSame([$argument], $paths[0]->memory->read($address)->operands);
        self::assertSame('int', $paths[0]->memory->read($address)->attributes['type']);
        self::assertSame($paths[0]->memory->read($address), $paths[0]->registers['result']);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('TypeError', $paths[1]->completion->value?->literal);
        self::assertSame($before, $paths[1]->memory->read($address));
        self::assertArrayNotHasKey('result', $paths[1]->registers);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }
    public function testWriteRejectsAnAbsentDeclarationWithoutTouchingStorage(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', null);
        $instruction = new Instruction('i', 'write', $source, 'result', ['address','value']);
        $paths = (new PropertyTransfer(new Machine($context)))->write($caller, $instruction, $state, $slot, $address);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $state->memory->cells);
        self::assertSame([], $state->registers);
    }
    public function testWriteConsumesOnlyTheSuccessfulReadonlyCloneOpportunity(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source, strict:true);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $state->memory->write($address, Term::constant(1));
        $state->memory->cloneWrites[$address->root] = ['x' => true,'other' => true];
        $state->registers['value'] = Term::constant(2);
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'b', new PropertyDeclaration('x', 'B', 'int', readonly:true));
        $instruction = new Instruction('i', 'write', $source, 'result', ['address','value']);
        $paths = (new PropertyTransfer(new Machine($context)))->write($caller, $instruction, $state, $slot, $address);
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame(2, $state->memory->read($address)->native());
        self::assertSame(['other' => true], $state->memory->cloneWrites[$address->root]);
        self::assertSame(2, $state->registers['result']->native());
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUncertainProperty')]
    public function testUncertainPreservesPresentAndAbsentPropertyStates(Term $value, string $reason): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $state->memory->write($address, $value);
        $state->addresses['address'] = $address;
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', 'int'));
        $instruction = new Instruction('i', 'read', $source, 'result', ['address']);
        $paths = (new PropertyTransfer(new Machine($context)))->uncertain($caller, $instruction, $state, $slot, $address);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame($reason, $paths[0]->registers['result']->literal);
        self::assertSame($value->operands, $paths[0]->registers['result']->operands);
        self::assertSame(['type' => 'int','origin' => 'custom'], $paths[0]->registers['result']->attributes);
        self::assertSame($value->secret, $paths[0]->registers['result']->secret);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('Error', $paths[1]->completion->value?->literal);
        self::assertSame('uninitialized', $paths[1]->memory->read($address)->kind);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }
    /**
     * @return iterable<string,array{Term,string}>
     */
    public static function providerUncertainProperty(): iterable
    {
        yield 'named' => [new Term('opaque', 'UNKNOWN_CALL', [Term::parameter('dependency')], ['type' => 'mixed','maybeUninitialized' => true,'origin' => 'custom'], true),'UNKNOWN_CALL'];
        yield 'unnamed' => [new Term('opaque', attributes:['type' => 'mixed','maybeUninitialized' => true,'origin' => 'custom']),'UNKNOWN_PROPERTY'];
    }
    public function testUncertainKeepsKnownPresenceWithoutForking(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'a.php', 0, 1);
        $caller = new CallableGraph('target', [], [], $source);
        $state = new State();
        $address = new Location('object:one', ['x']);
        $value = Term::constant(5);
        $state->memory->write($address, $value);
        $slot = new PropertySlot(new Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new PropertyDeclaration('x', 'B', 'int'));
        $instruction = new Instruction('i', 'read', $source, 'result', ['address']);
        $paths = (new PropertyTransfer(new Machine($context)))->uncertain($caller, $instruction, $state, $slot, $address);
        self::assertNull($paths);
        self::assertSame($value, $state->memory->read($address));
        self::assertSame([], $state->registers);
    }

}
