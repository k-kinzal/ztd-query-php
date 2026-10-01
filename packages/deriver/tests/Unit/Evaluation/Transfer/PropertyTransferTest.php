<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Memory\Location;
use Deriver\Reference\SourceRef;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyReference::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
