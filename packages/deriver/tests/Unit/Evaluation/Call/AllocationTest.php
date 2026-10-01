<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Reference\SourceRef;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\ProviderDispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
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
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(Configuration::class)]
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
#[UsesClass(\Deriver\Source\Declaration\Traits\Members::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\PropertyScope::class)]
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
#[UsesClass(\Deriver\Value\Lattice::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
