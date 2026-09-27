<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call;

use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Memory\Location;
use Deriver\Internal\Solver\Call\Allocation;
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
use Tests\Fake\SummaryFixture;

#[CoversClass(Allocation::class)]
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
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\DestructuringLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Members::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\PropertyScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\ProviderDispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
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
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
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
#[UsesClass(\Deriver\Internal\Value\Lattice::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
        $context = SolverFixture::context(configuration: new \Deriver\Api\Project\Configuration(stateSlots: [new \Deriver\Model\State\StateSlot('example.slot', 'int', Term::constant(1))]));
        $state = new State();
        $storage = new \Deriver\Internal\Solver\Model\StateStorage($context);

        $instruction = new Instruction('new', 'new', new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1));
        $allocation = new Allocation(new Machine($context));
        $a = $allocation->record($instruction, $state, 'Box', Term::constant('Box'), false);
        $b = $allocation->record($instruction, $state, 'Box', Term::constant('Box'), false);
        self::assertNotSame($a->literal, $b->literal);
        self::assertSame(1, $state->memory->read(new Location('model:' . $a->literal, ['example.slot']))->native());
    }

    public function testRecordClonesHeapAndModelStateWithTheirDeclaredSharingRules(): void
    {
        $context = SolverFixture::context(configuration:new \Deriver\Api\Project\Configuration(stateSlots:[new \Deriver\Model\State\StateSlot('example.copy', 'int', Term::constant(1)),new \Deriver\Model\State\StateSlot('example.reset', 'int', Term::constant(2), 'reset')]));
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
