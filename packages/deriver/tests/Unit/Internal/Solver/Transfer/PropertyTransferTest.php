<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Transfer;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Transfer\PropertyTransfer
 */
#[CoversClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
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
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
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
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->write($state->local('x'), \Deriver\Value\Term::constant(1));
        $result = (new \Deriver\Internal\Solver\Transfer\PropertyTransfer(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context())))->error($state, 'TypeError');
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
        $source = new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1);
        $caller = new \Deriver\Internal\IR\CallableIR('target', [], [], $source, strict:true);
        $state = new \Deriver\Internal\Solver\State();
        $address = new \Deriver\Internal\Memory\Location('object:one', ['x']);
        $before = \Deriver\Value\Term::constant(1);
        $state->memory->write($address, $before);
        $argument = \Deriver\Value\Term::parameter('input');
        $state->registers['value'] = $argument;
        $slot = new \Deriver\Internal\Solver\Transfer\PropertySlot(new \Deriver\Value\Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new \Deriver\Internal\IR\PropertyDeclaration('x', 'B', 'int'));
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'write', $source, 'result', ['address','value']);
        $paths = (new \Deriver\Internal\Solver\Transfer\PropertyTransfer(new \Deriver\Internal\Solver\Machine($context)))->write($caller, $instruction, $state, $slot, $address);
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
        $source = new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1);
        $caller = new \Deriver\Internal\IR\CallableIR('target', [], [], $source);
        $state = new \Deriver\Internal\Solver\State();
        $address = new \Deriver\Internal\Memory\Location('object:one', ['x']);
        $slot = new \Deriver\Internal\Solver\Transfer\PropertySlot(new \Deriver\Value\Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', null);
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'write', $source, 'result', ['address','value']);
        $paths = (new \Deriver\Internal\Solver\Transfer\PropertyTransfer(new \Deriver\Internal\Solver\Machine($context)))->write($caller, $instruction, $state, $slot, $address);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $state->memory->cells);
        self::assertSame([], $state->registers);
    }
    public function testWriteConsumesOnlyTheSuccessfulReadonlyCloneOpportunity(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1);
        $caller = new \Deriver\Internal\IR\CallableIR('target', [], [], $source, strict:true);
        $state = new \Deriver\Internal\Solver\State();
        $address = new \Deriver\Internal\Memory\Location('object:one', ['x']);
        $state->memory->write($address, \Deriver\Value\Term::constant(1));
        $state->memory->cloneWrites[$address->root] = ['x' => true,'other' => true];
        $state->registers['value'] = \Deriver\Value\Term::constant(2);
        $slot = new \Deriver\Internal\Solver\Transfer\PropertySlot(new \Deriver\Value\Term('object', 'one', attributes:['class' => 'B']), 'x', 'b', new \Deriver\Internal\IR\PropertyDeclaration('x', 'B', 'int', readonly:true));
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'write', $source, 'result', ['address','value']);
        $paths = (new \Deriver\Internal\Solver\Transfer\PropertyTransfer(new \Deriver\Internal\Solver\Machine($context)))->write($caller, $instruction, $state, $slot, $address);
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame(2, $state->memory->read($address)->native());
        self::assertSame(['other' => true], $state->memory->cloneWrites[$address->root]);
        self::assertSame(2, $state->registers['result']->native());
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUncertainProperty')]
    public function testUncertainPreservesPresentAndAbsentPropertyStates(\Deriver\Value\Term $value, string $reason): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1);
        $caller = new \Deriver\Internal\IR\CallableIR('target', [], [], $source);
        $state = new \Deriver\Internal\Solver\State();
        $address = new \Deriver\Internal\Memory\Location('object:one', ['x']);
        $state->memory->write($address, $value);
        $state->addresses['address'] = $address;
        $slot = new \Deriver\Internal\Solver\Transfer\PropertySlot(new \Deriver\Value\Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new \Deriver\Internal\IR\PropertyDeclaration('x', 'B', 'int'));
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'read', $source, 'result', ['address']);
        $paths = (new \Deriver\Internal\Solver\Transfer\PropertyTransfer(new \Deriver\Internal\Solver\Machine($context)))->uncertain($caller, $instruction, $state, $slot, $address);
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
     * @return iterable<string,array{\Deriver\Value\Term,string}>
     */
    public static function providerUncertainProperty(): iterable
    {
        yield 'named' => [new \Deriver\Value\Term('opaque', 'UNKNOWN_CALL', [\Deriver\Value\Term::parameter('dependency')], ['type' => 'mixed','maybeUninitialized' => true,'origin' => 'custom'], true),'UNKNOWN_CALL'];
        yield 'unnamed' => [new \Deriver\Value\Term('opaque', attributes:['type' => 'mixed','maybeUninitialized' => true,'origin' => 'custom']),'UNKNOWN_PROPERTY'];
    }
    public function testUncertainKeepsKnownPresenceWithoutForking(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1);
        $caller = new \Deriver\Internal\IR\CallableIR('target', [], [], $source);
        $state = new \Deriver\Internal\Solver\State();
        $address = new \Deriver\Internal\Memory\Location('object:one', ['x']);
        $value = \Deriver\Value\Term::constant(5);
        $state->memory->write($address, $value);
        $slot = new \Deriver\Internal\Solver\Transfer\PropertySlot(new \Deriver\Value\Term('object', 'one', attributes:['class' => 'B']), 'x', 'B', new \Deriver\Internal\IR\PropertyDeclaration('x', 'B', 'int'));
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'read', $source, 'result', ['address']);
        $paths = (new \Deriver\Internal\Solver\Transfer\PropertyTransfer(new \Deriver\Internal\Solver\Machine($context)))->uncertain($caller, $instruction, $state, $slot, $address);
        self::assertNull($paths);
        self::assertSame($value, $state->memory->read($address));
        self::assertSame([], $state->registers);
    }

}
