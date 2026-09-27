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
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
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
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
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
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(ArgumentBinding::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
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
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
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
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ArgumentBindingTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testActualsPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f($a,$b="default",...$rest){return [$a,$b,$rest];} function target(){return f(b:"B",a:"A",extra:3);}');
        self::assertSame(['A', 'B', ['extra' => 3]], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testBindMatchesNamedArgumentsBeforeEvaluatingTheCallee(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f($a,$b){return [$a,$b];}function target(){return f(b:2,a:1);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([1,2], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @param bool $readonly Whether the source is a readonly property
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReadonlyActuals')]
    public function testActualsPreservesNamesValuesAndReferencePermissions(bool $readonly): void
    {
        $state = new State();
        $state->registers['value'] = Term::constant('snapshot');
        $state->addresses['address'] = new Location('object:box', ['value']);
        $state->properties['address'] = new PropertySlot(new Term('object', 'box'), 'value', 'Box', new PropertyDeclaration('value', 'Box', readonly:$readonly));
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('value', 'named', location:'address')]);
        $actuals = (new ArgumentBinding(new Machine(SolverFixture::context())))->actuals($instruction, $state);
        self::assertCount(1, $actuals);
        self::assertSame('snapshot', $actuals[0]->value->native());
        self::assertSame('named', $actuals[0]->name);
        self::assertSame($state->addresses['address'], $actuals[0]->location);
        self::assertSame(!$readonly, $actuals[0]->writable);
    }

    /**
     * @return array<string,array{bool}>
     */
    public static function providerReadonlyActuals(): array
    {
        return ['readonly' => [true],'writable' => [false]];
    }

    public function testActualsUnpacksKeysAndReadsReferenceElementsFromCurrentMemory(): void
    {
        $state = new State();
        $cell = $state->memory->allocate(Term::constant('current'));
        $state->registers['array'] = Term::array([4 => Term::constant('first'),'named' => new Term('cell', $cell->root)]);
        $state->addresses['array-address'] = new Location('outer', ['nested']);
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('array', unpack:true, location:'array-address')]);
        $actuals = (new ArgumentBinding(new Machine(SolverFixture::context())))->actuals($instruction, $state);
        self::assertCount(2, $actuals);
        self::assertSame([null,'named'], array_column($actuals, 'name'));
        self::assertSame('first', $actuals[0]->value->native());
        self::assertSame('current', $actuals[1]->value->native());
        self::assertEquals(new Location('outer', ['nested',4]), $actuals[0]->location);
        self::assertEquals(new Location('outer', ['nested','named']), $actuals[1]->location);
        self::assertTrue($actuals[0]->writable);
        self::assertTrue($actuals[1]->writable);
    }

    public function testActualsDoesNotInventAddressesForLiteralArrayUnpacks(): void
    {
        $state = new State();
        $state->registers['array'] = Term::array([Term::constant(1),'x' => Term::constant(2)]);
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('array', unpack:true)]);
        $actuals = (new ArgumentBinding(new Machine(SolverFixture::context())))->actuals($instruction, $state);
        self::assertCount(2, $actuals);
        self::assertSame([null,null], array_column($actuals, 'location'));
        self::assertSame([null,'x'], array_column($actuals, 'name'));
    }

    /**
     * @param Term $value Unknown unpack sequence
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnknownUnpacks')]
    public function testActualsRetainsAnUnknownUnpackAsADependentRemainder(Term $value): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['input'] = $value;
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), arguments:[new Argument('input', unpack:true)]);
        $actuals = (new ArgumentBinding(new Machine($context)))->actuals($instruction, $state);
        self::assertCount(1, $actuals);
        self::assertSame('*', $actuals[0]->name);
        self::assertNull($actuals[0]->location);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $actuals[0]->value->literal);
        self::assertSame([$value], $actuals[0]->value->operands);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column($context->frontiers, 'code'));
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerUnknownUnpacks(): array
    {
        return ['symbolic' => [Term::parameter('items', 'array')],'open shape' => [Term::array(['known' => Term::constant(1)], true)]];
    }

    public function testBindRetainsCallerConditionsAndSeparatesCaptureModes(): void
    {
        $context = SolverFixture::context('<?php function target($x=3){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $caller = new State();
        $shared = $caller->memory->allocate(Term::constant(1));
        $caller->guard = ['condition' => true];
        $caller->constraints = ['input' => ['min' => 0,'max' => 10,'equal' => null,'excluded' => []]];
        $caller->controls = ['first','second'];
        $caller->observed = true;
        $caller->lateStaticClass = 'Caller';
        $receiver = new Term('object', 'box', attributes:['class' => 'Receiver']);
        $paths = (new ArgumentBinding(new Machine($context)))->bind($callable, $caller, [], $receiver, ['shared' => new Term('cell', $shared->root),'copy' => Term::constant(2)]);
        self::assertCount(1, $paths);
        $entry = $paths[0];
        self::assertSame($caller->guard, $entry->guard);
        self::assertSame($caller->constraints, $entry->constraints);
        self::assertSame($caller->controls, $entry->controls);
        self::assertTrue($entry->observed);
        self::assertSame('Receiver', $entry->lateStaticClass);
        self::assertSame($receiver, $entry->memory->read($entry->local('this')));
        self::assertSame(3, $entry->memory->read($entry->local('x'))->native());
        self::assertSame(2, $entry->memory->read($entry->local('copy'))->native());
        $entry->memory->write($entry->local('shared'), Term::constant(4));
        self::assertSame(4, $entry->memory->read($shared)->native());
        self::assertSame(1, $caller->memory->read($shared)->native());
        self::assertSame([], $caller->locals);
    }

    public function testBindPreservesTheCallingClassWithoutAReceiverAndStopsOnTheFirstError(): void
    {
        $context = SolverFixture::context('<?php function target(int $x,$later=3){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $caller = new State();
        $caller->lateStaticClass = 'Caller';
        $paths = (new ArgumentBinding(new Machine($context)))->bind($callable, $caller, [new PassedArgument(Term::array([]))]);
        self::assertCount(1, $paths);
        self::assertSame('Caller', $paths[0]->lateStaticClass);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
        self::assertSame([], $paths[0]->locals);
        self::assertSame('normal', $caller->completion->kind);
    }

    public function testBindRejectsArgumentOrderBeforeInitializingAnyParameter(): void
    {
        $context = SolverFixture::context('<?php function target($x,$y){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $paths = (new ArgumentBinding(new Machine($context)))->bind($callable, new State(), [new PassedArgument(Term::constant(1), 'x'),new PassedArgument(Term::constant(2))]);
        self::assertCount(1, $paths);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $paths[0]->locals);
    }
}
