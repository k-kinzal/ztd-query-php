<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Api;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Query\ValueQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ExpressionRef::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ResidualPaths::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class QueryExecutionTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDerivePreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){ $x=1; $x++; return $x; }');
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target', budget: new \Deriver\Api\Query\Budget(transfers: 1)));
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('BUDGET_EXCEEDED', $result->frontiers[0]->code);
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
    }
    public function testEntryUsesDefaultsOnlyInConcreteScope(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target($n=3){return $n;}');
        $snapshot = new \Deriver\Api\Project\ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new \Deriver\Internal\Api\QueryExecution($context->program, $context->configuration, $context->models, $snapshot);

        $execution->entry($context, new \Deriver\Api\Project\EntryPoint('target'), false);
        self::assertSame(3, $context->normal[0]->values['return']->native());
    }
    public function testEntryKeepsExternalParametersSymbolicDespiteTheirDefaults(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target($n=3){return $n;}');
        $snapshot = new \Deriver\Api\Project\ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new \Deriver\Internal\Api\QueryExecution($context->program, $context->configuration, $context->models, $snapshot);

        $execution->entry($context, new \Deriver\Api\Project\EntryPoint('target'), true);
        self::assertSame('parameter', $context->normal[0]->values['return']->kind);
    }
    public function testOwnerRejectsFabricatedInstructionReferences(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php function target($n=3){return $n;}');
        $snapshot = new \Deriver\Api\Project\ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new \Deriver\Internal\Api\QueryExecution($context->program, $context->configuration, $context->models, $snapshot);

        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $execution->owner(new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1), 'target', 'fabricated')));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveRetainsEveryDeclaredEntrypointAndItsArguments(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(int $a,int $b=2){return [$a,$b];}');
        $query = new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([
            new \Deriver\Api\Project\EntryPoint('target', [\Deriver\Value\Term::constant(1)]),
            new \Deriver\Api\Project\EntryPoint('target', ['b' => \Deriver\Value\Term::constant(4),'a' => \Deriver\Value\Term::constant(3)]),
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
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('entry')])));
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
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('missing'));
        self::assertCount(1, $result->frontiers);
        self::assertSame('INCOMPLETE_SOURCE', $result->frontiers[0]->code);
        self::assertSame('INCOMPLETE_DERIVATION', $result->normalOutcomes[0]->values['residual']->literal);
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('may-reach', $result->reachability);
        self::assertSame(0, $result->statistics->transfers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @param array<int|string,\Deriver\Value\Term> $arguments Explicit entry inputs
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidEntryArguments')]
    public function testEntryReportsBindingErrorsWithoutRunningTheBody(array $arguments, string $exception): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(int $a){return 99;}');
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('target', $arguments)])));
        self::assertSame([], $result->normalOutcomes);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->exceptionalOutcomes);
        self::assertSame($exception, $result->exceptionalOutcomes[0]->exception->literal);
        self::assertSame('may-reach', $result->reachability);
        self::assertSame(0, $result->statistics->transfers);
    }
    /**
     * @return iterable<string,array{array<int|string,\Deriver\Value\Term>,string}>
     */
    public static function providerInvalidEntryArguments(): iterable
    {
        yield 'missing' => [[],'ArgumentCountError'];
        yield 'invalid type' => [[\Deriver\Value\Term::array([])],'TypeError'];
        yield 'unknown name' => [['unknown' => \Deriver\Value\Term::constant(1)],'Error'];
        yield 'duplicate' => [[\Deriver\Value\Term::constant(1),'a' => \Deriver\Value\Term::constant(2)],'Error'];
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntryUsesTheSuppliedInstanceReceiverAndIgnoresItForStaticMethods(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php class B{function instance(){return $this;}static function target(){return isset($this);}}');
        $receiver = new \Deriver\Value\Term('object', 'provided', attributes:['class' => 'B']);
        $instance = $session->derive(new \Deriver\Api\Query\ReturnQuery('B::instance', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('B::instance', receiver:$receiver)])));
        $static = $session->derive(new \Deriver\Api\Query\ReturnQuery('B::target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('B::target', receiver:$receiver)])));
        self::assertSame($receiver, $instance->normalOutcomes[0]->values['return']);
        self::assertSame([], $instance->frontiers);
        self::assertSame([], $static->frontiers);
        self::assertFalse($static->normalOutcomes[0]->values['return']->native());
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnregisteredSlotQuery')]
    public function testOwnerRejectsUnregisteredStateSlotsBeforeReferenceLookup(\Deriver\Api\Query\Query $query): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $snapshot = new \Deriver\Api\Project\ProjectSnapshot('test', [], [], $context->configuration->target, false, 'none');
        $execution = new \Deriver\Internal\Api\QueryExecution($context->program, $context->configuration, $context->models, $snapshot);
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('Projection requires a registered state slot: example.missing');
        $execution->owner($query);
    }
    /**
     * @return iterable<string,array{\Deriver\Api\Query\Query}>
     */
    public static function providerUnregisteredSlotQuery(): iterable
    {
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        yield 'value' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r'), \Deriver\Value\Projection::stateSlot('example.missing'))];
        yield 'state' => [new \Deriver\Api\Query\StateQuery(new \Deriver\Api\Reference\PointRef($source, 'target', 'i', 'after'), 'value', \Deriver\Value\Projection::stateSlot('example.missing'))];
    }
    public function testOwnerAllowsARegisteredStateSlotOnAValidValueReference(): void
    {
        $configuration = new \Deriver\Api\Project\Configuration(stateSlots:[new \Deriver\Model\State\StateSlot('example.state')]);
        $context = \Tests\Fake\SolverFixture::context(configuration:$configuration);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = $body->blocks[0]->instructions[0];
        $snapshot = new \Deriver\Api\Project\ProjectSnapshot('test', [], [], $configuration->target, false, 'none');
        $execution = new \Deriver\Internal\Api\QueryExecution($context->program, $configuration, $context->models, $snapshot);
        $query = new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($instruction->source, 'target', $instruction->result), \Deriver\Value\Projection::stateSlot('example.state'));
        self::assertSame('target', $execution->owner($query));
    }

}
