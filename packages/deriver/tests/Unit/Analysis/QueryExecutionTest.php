<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\ResidualPaths;
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
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Exception\InvalidInputException;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
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
use Deriver\Query\Query;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\PointRef;
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
use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
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
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(ResidualPaths::class)]
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
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
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
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
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
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(DestructuringLowering::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class QueryExecutionTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDerivePreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){ $x=1; $x++; return $x; }');
        $result = $session->derive(new ReturnQuery('target', budget: new Budget(transfers: 1)));
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('BUDGET_EXCEEDED', $result->frontiers[0]->code);
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
    }
    public function testEntryUsesDefaultsOnlyInConcreteScope(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target($n=3){return $n;}');
        $snapshot = new ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $context->configuration, $context->models, $snapshot);

        $execution->entry($context, new EntryPoint('target'), false);
        self::assertSame(3, $context->normal[0]->values['return']->native());
    }
    public function testEntryKeepsExternalParametersSymbolicDespiteTheirDefaults(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target($n=3){return $n;}');
        $snapshot = new ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $context->configuration, $context->models, $snapshot);

        $execution->entry($context, new EntryPoint('target'), true);
        self::assertSame('parameter', $context->normal[0]->values['return']->kind);
    }
    public function testOwnerRejectsFabricatedInstructionReferences(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target($n=3){return $n;}');
        $snapshot = new ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $context->configuration, $context->models, $snapshot);

        $this->expectException(InvalidInputException::class);
        $execution->owner(new ValueQuery(new ExpressionRef(new SourceRef('test', 'fixture.php', 0, 1), 'target', 'fabricated')));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveRetainsEveryDeclaredEntrypointAndItsArguments(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(int $a,int $b=2){return [$a,$b];}');
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([
            new EntryPoint('target', [Term::constant(1)]),
            new EntryPoint('target', ['b' => Term::constant(4),'a' => Term::constant(3)]),
        ]));
        $result = $session->derive($query);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(2, $result->normalOutcomes);
        self::assertSame([1,2], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([3,4], $result->normalOutcomes[1]->values['return']->native());
        self::assertSame('may-reach', $result->reachability);
        self::assertSame($query, $result->query);
        self::assertSame($session->snapshot()->id, $result->snapshotId);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveDistinguishesAnUnreachedCallableFromAnIncompleteEntry(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 1;}function entry(){return 2;}');
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('entry')])));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->normalOutcomes);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame('unreachable', $result->reachability);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveAddsAResidualWhenEntrySourceIsMissing(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 1;}');
        $result = $session->derive(new ReturnQuery('missing'));
        self::assertCount(1, $result->frontiers);
        self::assertSame('INCOMPLETE_SOURCE', $result->frontiers[0]->code);
        self::assertSame('INCOMPLETE_DERIVATION', $result->normalOutcomes[0]->values['residual']->literal);
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('may-reach', $result->reachability);
        self::assertSame(0, $result->statistics->transfers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @param array<int|string,Term> $arguments Explicit entry inputs
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidEntryArguments')]
    public function testEntryReportsBindingErrorsWithoutRunningTheBody(array $arguments, string $exception): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(int $a){return 99;}');
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', $arguments)])));
        self::assertSame([], $result->normalOutcomes);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->exceptionalOutcomes);
        self::assertSame($exception, $result->exceptionalOutcomes[0]->exception->literal);
        self::assertSame('may-reach', $result->reachability);
        self::assertSame(0, $result->statistics->transfers);
    }
    /**
     * @return iterable<string,array{array<int|string,Term>,string}>
     */
    public static function providerInvalidEntryArguments(): iterable
    {
        yield 'missing' => [[],'ArgumentCountError'];
        yield 'invalid type' => [[Term::array([])],'TypeError'];
        yield 'unknown name' => [['unknown' => Term::constant(1)],'Error'];
        yield 'duplicate' => [[Term::constant(1),'a' => Term::constant(2)],'Error'];
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntryUsesTheSuppliedInstanceReceiverAndIgnoresItForStaticMethods(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php class B{function instance(){return $this;}static function target(){return isset($this);}}');
        $receiver = new Term('object', 'provided', attributes:['class' => 'B']);
        $instance = $session->derive(new ReturnQuery('B::instance', QueryScope::fromEntrypoints([new EntryPoint('B::instance', receiver:$receiver)])));
        $static = $session->derive(new ReturnQuery('B::target', QueryScope::fromEntrypoints([new EntryPoint('B::target', receiver:$receiver)])));
        self::assertSame($receiver, $instance->normalOutcomes[0]->values['return']);
        self::assertSame([], $instance->frontiers);
        self::assertSame([], $static->frontiers);
        self::assertFalse($static->normalOutcomes[0]->values['return']->native());
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnregisteredSlotQuery')]
    public function testOwnerRejectsUnregisteredStateSlotsBeforeReferenceLookup(Query $query): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $snapshot = new ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $context->configuration, $context->models, $snapshot);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Projection requires a registered state slot: example.missing');
        $execution->owner($query);
    }
    /**
     * @return iterable<string,array{Query}>
     */
    public static function providerUnregisteredSlotQuery(): iterable
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        yield 'value' => [new ValueQuery(new ExpressionRef($source, 'target', 'r'), Projection::stateSlot('example.missing'))];
        yield 'state' => [new StateQuery(new PointRef($source, 'target', 'i', 'after'), 'value', Projection::stateSlot('example.missing'))];
    }
    public function testOwnerAllowsARegisteredStateSlotOnAValidValueReference(): void
    {
        $configuration = new Configuration(stateSlots:[new StateSlot('example.state')]);
        $context = \Tests\Fake\SolverFixture::context(configuration:$configuration);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = $body->blocks[0]->instructions[0];
        $snapshot = new ProjectSnapshot('test', [], [], $configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $configuration, $context->models, $snapshot);
        $query = new ValueQuery(new ExpressionRef($instruction->source, 'target', $instruction->result), Projection::stateSlot('example.state'));
        self::assertSame('target', $execution->owner($query));
    }

}
