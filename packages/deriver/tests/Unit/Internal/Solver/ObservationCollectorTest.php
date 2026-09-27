<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\Query::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Query\StateQuery::class)]
#[UsesClass(\Deriver\Api\Query\TupleQuery::class)]
#[UsesClass(\Deriver\Api\Query\ValueQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ExpressionRef::class)]
#[UsesClass(\Deriver\Api\Reference\Observation::class)]
#[UsesClass(\Deriver\Api\Reference\PointRef::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\CallObservations::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallSiteIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\AbstractDomain::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\State\StateSlot::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ObservationCollectorTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInstructionPreservesTheSemanticContract(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php $x=1; sink($x++, $x);');
        $observation = $session->callsTo('sink')[0];
        $result = $session->derive(new \Deriver\Api\Query\TupleQuery($observation->beforeInvocation(), ['before' => $observation->argument(0), 'after' => $observation->argument(1)]));
        self::assertSame(1, $result->normalOutcomes[0]->values['before']->native());
        self::assertSame(2, $result->normalOutcomes[0]->values['after']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testCompletionKeepsTheExceptionalStateAtTheThrow(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->write($state->local('x'), \Deriver\Value\Term::constant(2));
        $state->completion = new \Deriver\Internal\Solver\Completion('throw', new \Deriver\Value\Term('throwable', 'Error'));
        (new \Deriver\Internal\Solver\ObservationCollector($context))->completion($body, $state);
        self::assertSame([], $context->normal);
        self::assertSame(2, $context->exceptional[0]->state['x']->native());
    }
    public function testProjectFollowsObjectIdentityWithoutDroppingSiblingFields(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:one'] = \Deriver\Value\Term::fromNative(['x' => ['nested' => 3],'sibling' => 7]);
        $value = (new \Deriver\Internal\Solver\ObservationCollector(\Tests\Fake\SolverFixture::context()))->project(new \Deriver\Value\Term('object', 'one'), ['x','nested'], $state);
        self::assertSame(3, $value->native());
        self::assertSame(7, $state->memory->cells['object:one']->operands['sibling']->native());
    }

    /**
     * @param \Deriver\Value\Term $input Aggregate containing the selected field
     * @param list<int|string> $path Requested field path
     * @param bool $secret Whether the selected value must remain confidential
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerProjectedConfidentiality')]
    public function testProjectCarriesOnlySelectedContainerAndValueConfidentiality(\Deriver\Value\Term $input, array $path, bool $secret): void
    {
        $value = (new \Deriver\Internal\Solver\ObservationCollector(\Tests\Fake\SolverFixture::context()))->project($input, $path, new \Deriver\Internal\Solver\State());
        self::assertSame('selected', $value->native());
        self::assertSame($secret, $value->isSecret());
    }

    /**
     * @return iterable<string,array{\Deriver\Value\Term,list<int|string>,bool}>
     */
    public static function providerProjectedConfidentiality(): iterable
    {
        $plain = \Deriver\Value\Term::constant('selected');
        $private = \Deriver\Value\Term::constant('selected', true);
        yield 'public field' => [\Deriver\Value\Term::array(['keep' => $plain]),['keep'],false];
        yield 'secret field' => [\Deriver\Value\Term::array(['keep' => $private]),['keep'],true];
        yield 'secret sibling does not label public field' => [\Deriver\Value\Term::array(['keep' => $plain,'private' => $private]),['keep'],false];
        yield 'explicit wrapper' => [new \Deriver\Value\Term('array', operands:['keep' => $plain], secret:true),['keep'],true];
        yield 'nested wrapper' => [\Deriver\Value\Term::array(['outer' => new \Deriver\Value\Term('array', operands:['keep' => $plain], secret:true)]),['outer','keep'],true];
        yield 'outer wrapper' => [new \Deriver\Value\Term('array', operands:['outer' => \Deriver\Value\Term::array(['keep' => $plain])], secret:true),['outer','keep'],true];
        yield 'public scalar identity' => [$plain,[],false];
        yield 'secret scalar identity' => [$private,[],true];
    }

    public function testProjectCarriesConfidentialObjectAndReferenceHandles(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant('selected')]);
        $state->memory->cells['ref'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant('selected')]);
        $collector = new \Deriver\Internal\Solver\ObservationCollector(\Tests\Fake\SolverFixture::context());
        $object = $collector->project(new \Deriver\Value\Term('object', 'box', secret:true), ['value'], $state);
        $reference = $collector->project(new \Deriver\Value\Term('cell', 'ref', secret:true), ['value'], $state);
        self::assertSame('selected', $object->native());
        self::assertSame('selected', $reference->native());
        self::assertTrue($object->isSecret());
        self::assertTrue($reference->isSecret());
    }

    public function testProjectCarriesConfidentialityThroughAbstractSlots(): void
    {
        $config = new \Deriver\Api\Project\Configuration(stateSlots:[new \Deriver\Model\State\StateSlot('example.cache', 'array')]);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['model:box'] = \Deriver\Value\Term::array(['example.cache' => \Deriver\Value\Term::fromNative(['keep' => 'selected'])]);
        $value = (new \Deriver\Internal\Solver\ObservationCollector(\Tests\Fake\SolverFixture::context(configuration:$config)))->project(new \Deriver\Value\Term('object', 'box', secret:true), ['keep'], $state, 'example.cache');
        self::assertSame('selected', $value->native());
        self::assertTrue($value->isSecret());
    }

    public function testProjectRetainsUnknownDomainAndMissingFieldDependencies(): void
    {
        $collector = new \Deriver\Internal\Solver\ObservationCollector(\Tests\Fake\SolverFixture::context());
        $domain = new \Deriver\Value\Term('domain', 'missing.domain', secret:true);
        $unknown = $collector->project($domain, ['field'], new \Deriver\Internal\Solver\State());
        $array = \Deriver\Value\Term::array(['public' => \Deriver\Value\Term::constant(1)]);
        $absent = $collector->project($array, ['absent'], new \Deriver\Internal\Solver\State());
        self::assertSame('projection', $unknown->kind);
        self::assertSame([$domain], $unknown->operands);
        self::assertTrue($unknown->isSecret());
        self::assertSame('array-read', $absent->kind);
        self::assertSame($array, $absent->operands[0]);
        self::assertSame('absent', $absent->operands[1]->native());
    }

    public function testProjectDelegatesRegisteredDomainProjectionWithoutErasingConfidentiality(): void
    {
        $config = new \Deriver\Api\Project\Configuration(domains:[new \Tests\Fake\PolicyDomain()]);
        $input = new \Deriver\Value\Term('domain', 'example.policy', ['representation' => \Deriver\Value\Term::fromNative(['keep' => 'selected'])], secret:true);
        $value = (new \Deriver\Internal\Solver\ObservationCollector(\Tests\Fake\SolverFixture::context(configuration:$config)))->project($input, ['keep'], new \Deriver\Internal\Solver\State());
        self::assertSame('domain', $value->kind);
        self::assertSame('example.policy', $value->literal);
        self::assertSame('selected', $value->operands['representation']->native());
        self::assertTrue($value->isSecret());
    }

    /**
     * @param \Deriver\Api\Query\Query $query Requested observation
     * @param string $phase Executed phase
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnmatchedObservations')]
    public function testInstructionIgnoresDifferentOwnersRegistersPointsAndPhases(\Deriver\Api\Query\Query $query, string $phase): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $body = new \Deriver\Internal\IR\CallableIR('target', [], [], $source);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['r1'] = \Deriver\Value\Term::constant(7);
        (new \Deriver\Internal\Solver\ObservationCollector($context))->instruction($body, new \Deriver\Internal\IR\Instruction('i', 'constant', $source, 'r1'), $state, $phase);
        self::assertSame([], $context->normal);
        self::assertFalse($state->observed);
        self::assertSame('normal', $state->completion->kind);
    }

    /**
     * @return iterable<string,array{\Deriver\Api\Query\Query,string}>
     */
    public static function providerUnmatchedObservations(): iterable
    {
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        yield 'value before execution' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r1')),'before'];
        yield 'value at invocation' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r1')),'invocation'];
        yield 'value different owner' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'other', 'r1')),'after'];
        yield 'value different register' => [new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r2')),'after'];
        yield 'state different owner' => [new \Deriver\Api\Query\StateQuery(new \Deriver\Api\Reference\PointRef($source, 'other', 'i', 'before'), 'x'),'before'];
        yield 'state different instruction' => [new \Deriver\Api\Query\StateQuery(new \Deriver\Api\Reference\PointRef($source, 'target', 'other', 'before'), 'x'),'before'];
        yield 'state different phase' => [new \Deriver\Api\Query\StateQuery(new \Deriver\Api\Reference\PointRef($source, 'target', 'i', 'before'), 'x'),'after'];
        yield 'return is not an instruction observation' => [new \Deriver\Api\Query\ReturnQuery('target'),'after'];
    }

    /**
     * @param \Deriver\Api\Query\QueryScope $scope Requested invocation scope
     * @param string $completion Completion expected after an observation
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerObservationScopes')]
    public function testInstructionCapturesValueStateGuardAndEvidenceInTheRequestedScope(\Deriver\Api\Query\QueryScope $scope, string $completion): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $query = new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r1'), scope:$scope);
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['r1'] = \Deriver\Value\Term::constant(7);
        $state->guard = ['branch' => true];
        $state->evidence = ['e1'];
        $state->memory->write($state->local('x'), \Deriver\Value\Term::constant(3));
        (new \Deriver\Internal\Solver\ObservationCollector($context))->instruction(new \Deriver\Internal\IR\CallableIR('target', [], [], $source), new \Deriver\Internal\IR\Instruction('i', 'constant', $source, 'r1'), $state, 'after');
        self::assertCount(1, $context->normal);
        self::assertSame(7, $context->normal[0]->values['value']->native());
        self::assertSame(3, $context->normal[0]->state['x']->native());
        self::assertSame(['branch' => true], $context->normal[0]->guard);
        self::assertSame(['e1'], $context->normal[0]->evidence);
        self::assertTrue($state->observed);
        self::assertSame($completion, $state->completion->kind);
        self::assertArrayHasKey('x', $context->normal[0]->storage->bindings);
    }

    /**
     * @return iterable<string,array{\Deriver\Api\Query\QueryScope,string}>
     */
    public static function providerObservationScopes(): iterable
    {
        yield 'symbolic slice ends here' => [\Deriver\Api\Query\QueryScope::symbolic(),'observed'];
        yield 'entrypoint execution continues' => [\Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('target')]),'normal'];
    }

    /**
     * @param \Deriver\Api\Query\Query $query Requested state or tuple
     * @param array<string,int> $expected Captured fields
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStateAndTupleQueries')]
    public function testInstructionCapturesStateAndTupleValuesAtTheMatchingPoint(\Deriver\Api\Query\Query $query, array $expected): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->write($state->local('x'), \Deriver\Value\Term::fromNative(['field' => 3]));
        $state->memory->cells['ref'] = \Deriver\Value\Term::constant(9);
        $state->registers['r1'] = \Deriver\Value\Term::constant(7);
        $state->registers['r2'] = new \Deriver\Value\Term('cell', 'ref');
        (new \Deriver\Internal\Solver\ObservationCollector($context))->instruction(new \Deriver\Internal\IR\CallableIR('target', [], [], $source), new \Deriver\Internal\IR\Instruction('i', 'constant', $source, 'r1'), $state, 'before');
        self::assertCount(1, $context->normal);
        self::assertSame($expected, array_map(static fn (\Deriver\Value\Term $value): int|float|string|bool|null => $value->literal, $context->normal[0]->values));
        self::assertSame(['field' => 3], $context->normal[0]->state['x']->native());
    }

    /**
     * @return iterable<string,array{\Deriver\Api\Query\Query,array<string,int>}>
     */
    public static function providerStateAndTupleQueries(): iterable
    {
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $point = new \Deriver\Api\Reference\PointRef($source, 'target', 'i', 'before');
        $qualified = new \Deriver\Api\Reference\PointRef($source, '\\TARGET', 'i', 'before');
        yield 'qualified state' => [new \Deriver\Api\Query\StateQuery($qualified, 'x', new \Deriver\Value\Projection(['field'])),['state' => 3]];
        yield 'qualified tuple' => [new \Deriver\Api\Query\TupleQuery($qualified, ['first' => new \Deriver\Api\Reference\ExpressionRef($source, 'TARGET', 'r1')]),['first' => 7]];
        yield 'projected state' => [new \Deriver\Api\Query\StateQuery($point, 'x', new \Deriver\Value\Projection(['field'])),['state' => 3]];
        yield 'correlated tuple' => [new \Deriver\Api\Query\TupleQuery($point, ['first' => new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r1'),'second' => new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r2')]),['first' => 7,'second' => 9]];
    }

    /**
     * @param string $completion Return or throw completion
     * @param bool $observed Whether the requested point was reached
     * @param string $entry Entry owning pre-observation errors
     * @param array<string,int> $active Active call counts
     * @param int $exceptions Number of externally visible exceptions
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPreObservationCompletions')]
    public function testCompletionExportsOnlyEscapingPreObservationExceptions(string $completion, bool $observed, string $entry, array $active, int $exceptions): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $query = new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, 'target', 'r1'));
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $context->entrySymbol = $entry;
        $context->active = $active;
        $state = new \Deriver\Internal\Solver\State();
        $state->observed = $observed;
        $state->completion = new \Deriver\Internal\Solver\Completion($completion, new \Deriver\Value\Term('throwable', 'Error'));
        (new \Deriver\Internal\Solver\ObservationCollector($context))->completion(new \Deriver\Internal\IR\CallableIR('target', [], [], $source), $state);
        self::assertCount($exceptions, $context->exceptional);
        self::assertSame([], $context->normal);
    }

    /**
     * @return iterable<string,array{string,bool,string,array<string,int>,int}>
     */
    public static function providerPreObservationCompletions(): iterable
    {
        yield 'entry before point' => ['throw',false,'target',['target' => 1],1];
        yield 'entry no active count' => ['throw',false,'target',[],1];
        yield 'point already reached' => ['throw',true,'target',['target' => 1],0];
        yield 'different entry' => ['throw',false,'other',['target' => 1],0];
        yield 'recursive entry' => ['throw',false,'target',['target' => 2],0];
        yield 'called within another entry' => ['throw',false,'target',['target' => 1,'other' => 1],0];
        yield 'ordinary return' => ['return',false,'target',['target' => 1],0];
    }

    /**
     * @param string $symbol Requested function name
     * @param \Deriver\Api\Query\QueryScope $scope Invocation scope
     * @param int $active Recursive invocation count
     * @param int $outcomes Expected visible returns
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReturnCompletions')]
    public function testCompletionSelectsReturnSymbolsAndScopeWithoutDuplicatingRecursiveRoots(string $symbol, \Deriver\Api\Query\QueryScope $scope, int $active, int $outcomes): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $query = new \Deriver\Api\Query\ReturnQuery($symbol, $scope);
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $context->active['target'] = $active;
        $state = new \Deriver\Internal\Solver\State();
        $state->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(8));
        (new \Deriver\Internal\Solver\ObservationCollector($context))->completion(new \Deriver\Internal\IR\CallableIR('TaRgEt', [], [], new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1)), $state);
        self::assertCount($outcomes, $context->normal);
        self::assertSame([], $context->exceptional);
    }

    /**
     * @return iterable<string,array{string,\Deriver\Api\Query\QueryScope,int,int}>
     */
    public static function providerReturnCompletions(): iterable
    {
        yield 'case insensitive match' => ['TARGET',\Deriver\Api\Query\QueryScope::symbolic(),1,1];
        yield 'other callable' => ['other',\Deriver\Api\Query\QueryScope::symbolic(),1,0];
        yield 'recursive root suppressed' => ['target',\Deriver\Api\Query\QueryScope::symbolic(),2,0];
        yield 'entrypoint nested return' => ['target',\Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('target')]),2,1];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNamedOwnerVariants')]
    public function testInstructionNormalizesNamedOwnersBeforeCapturingValues(string $owner): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $query = new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($source, $owner, 'r1'));
        $context = new \Deriver\Internal\Solver\Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['r1'] = \Deriver\Value\Term::constant(7);
        (new \Deriver\Internal\Solver\ObservationCollector($context))->instruction(new \Deriver\Internal\IR\CallableIR('N\\target', [], [], $source), new \Deriver\Internal\IR\Instruction('i', 'constant', $source, 'r1'), $state, 'after');
        self::assertCount(1, $context->normal);
        self::assertSame(7, $context->normal[0]->values['value']->native());
        self::assertTrue($state->observed);
        self::assertSame('observed', $state->completion->kind);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerNamedOwnerVariants(): iterable
    {
        yield 'declaration' => ['N\\target'];
        yield 'case variant' => ['n\\TARGET'];
        yield 'qualified variant' => ['\\N\\TARGET'];
    }
}
