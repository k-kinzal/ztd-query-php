<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\QueryExecution;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Budget;
use Deriver\Query\Query;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\PointRef;
use Deriver\Reference\SourceRef;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\ResidualPaths::class)]
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
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[UsesClass(\Deriver\Evaluation\Call\SymbolicEnums::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\EntryProperties::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
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
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntryInstallsSuppliedReceiverPropertiesOnlyForInstanceEntries(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php final class B{private string $order="name";function sql(){return "ORDER BY ".$this->order;}static function make(){return 1;}}');
        $scope = QueryScope::fromEntrypoints([new EntryPoint('B::sql', properties:['order' => Term::constant('email')]), new EntryPoint('B::sql')]);
        $result = $session->derive(new ReturnQuery('B::sql', $scope));
        self::assertSame('ORDER BY email', $result->normalOutcomes[0]->values['return']->native());
        self::assertFalse($result->normalOutcomes[1]->values['return']->isConcrete());
        $this->expectException(InvalidInputException::class);
        $session->derive(new ReturnQuery('B::make', QueryScope::fromEntrypoints([new EntryPoint('B::make', properties:['order' => Term::constant('email')])])));
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

    public function testInitialStateCapturesGlobalsBeforeEffects(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $configuration = new Configuration(environment: ['global:db' => Term::parameter('db', 'DB')]);
        $snapshot = new ProjectSnapshot('test', [], [], $configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $configuration, $context->models, $snapshot);
        self::assertSame('DB', $execution->initialState()->memory->cells['global:db']->attributes['type']);
    }

    /**
     * @throws JsonException If query metadata cannot be encoded
     */
    public function testResultKeepsSharedExecutionIdentitySeparateFromIndependentQueries(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $snapshot = new ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $context->configuration, $context->models, $snapshot);
        $first = $execution->result($context, 'target', microtime(true));
        $batch = $execution->result($context, 'target', microtime(true), ':batch:example');
        self::assertNotSame($first->reference->id, $batch->reference->id);
        self::assertSame('unreachable', $batch->reachability);
    }

    /**
     * @throws JsonException If query metadata cannot be encoded
     */
    public function testTogetherRejectsForeignReferencesBeforeRunningAnyEntry(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $snapshot = new ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new QueryExecution($context->program, $context->configuration, $context->models, $snapshot);
        $this->expectException(InvalidInputException::class);
        $execution->together([new ReturnQuery('target'), new ValueQuery(new ExpressionRef(new SourceRef('foreign', 'a.php', 0, 1), 'target', 'r0'))]);
    }

}
