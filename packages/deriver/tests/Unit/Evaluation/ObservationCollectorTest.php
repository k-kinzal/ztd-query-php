<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\State;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\Query;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
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

#[CoversClass(ObservationCollector::class)]
#[UsesClass(\Deriver\Analysis\CallObservations::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\Domain\DomainOperations::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(StateSlot::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(StateQuery::class)]
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
#[UsesClass(\Deriver\Reference\Observation::class)]
#[UsesClass(PointRef::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallSiteIndex::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
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
        $result = $session->derive(new TupleQuery($observation->beforeInvocation(), ['before' => $observation->argument(0), 'after' => $observation->argument(1)]));
        self::assertSame(1, $result->normalOutcomes[0]->values['before']->native());
        self::assertSame(2, $result->normalOutcomes[0]->values['after']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testCompletionKeepsTheExceptionalStateAtTheThrow(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(2));
        $state->completion = new Completion('throw', new Term('throwable', 'Error'));
        (new ObservationCollector($context))->completion($body, $state);
        self::assertSame([], $context->normal);
        self::assertSame(2, $context->exceptional[0]->state['x']->native());
    }
    public function testProjectFollowsObjectIdentityWithoutDroppingSiblingFields(): void
    {
        $state = new State();
        $state->memory->cells['object:one'] = Term::fromNative(['x' => ['nested' => 3],'sibling' => 7]);
        $value = (new ObservationCollector(\Tests\Fake\SolverFixture::context()))->project(new Term('object', 'one'), ['x','nested'], $state);
        self::assertSame(3, $value->native());
        self::assertSame(7, $state->memory->cells['object:one']->operands['sibling']->native());
    }

    /**
     * @param Term $input Aggregate containing the selected field
     * @param list<int|string> $path Requested field path
     * @param bool $secret Whether the selected value must remain confidential
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerProjectedConfidentiality')]
    public function testProjectCarriesOnlySelectedContainerAndValueConfidentiality(Term $input, array $path, bool $secret): void
    {
        $value = (new ObservationCollector(\Tests\Fake\SolverFixture::context()))->project($input, $path, new State());
        self::assertSame('selected', $value->native());
        self::assertSame($secret, $value->isSecret());
    }

    /**
     * @return iterable<string,array{Term,list<int|string>,bool}>
     */
    public static function providerProjectedConfidentiality(): iterable
    {
        $plain = Term::constant('selected');
        $private = Term::constant('selected', true);
        yield 'public field' => [Term::array(['keep' => $plain]),['keep'],false];
        yield 'secret field' => [Term::array(['keep' => $private]),['keep'],true];
        yield 'secret sibling does not label public field' => [Term::array(['keep' => $plain,'private' => $private]),['keep'],false];
        yield 'explicit wrapper' => [new Term('array', operands:['keep' => $plain], secret:true),['keep'],true];
        yield 'nested wrapper' => [Term::array(['outer' => new Term('array', operands:['keep' => $plain], secret:true)]),['outer','keep'],true];
        yield 'outer wrapper' => [new Term('array', operands:['outer' => Term::array(['keep' => $plain])], secret:true),['outer','keep'],true];
        yield 'public scalar identity' => [$plain,[],false];
        yield 'secret scalar identity' => [$private,[],true];
    }

    public function testProjectCarriesConfidentialObjectAndReferenceHandles(): void
    {
        $state = new State();
        $state->memory->cells['object:box'] = Term::array(['value' => Term::constant('selected')]);
        $state->memory->cells['ref'] = Term::array(['value' => Term::constant('selected')]);
        $collector = new ObservationCollector(\Tests\Fake\SolverFixture::context());
        $object = $collector->project(new Term('object', 'box', secret:true), ['value'], $state);
        $reference = $collector->project(new Term('cell', 'ref', secret:true), ['value'], $state);
        self::assertSame('selected', $object->native());
        self::assertSame('selected', $reference->native());
        self::assertTrue($object->isSecret());
        self::assertTrue($reference->isSecret());
    }

    public function testProjectCarriesConfidentialityThroughAbstractSlots(): void
    {
        $config = new Configuration(stateSlots:[new StateSlot('example.cache', 'array')]);
        $state = new State();
        $state->memory->cells['model:box'] = Term::array(['example.cache' => Term::fromNative(['keep' => 'selected'])]);
        $value = (new ObservationCollector(\Tests\Fake\SolverFixture::context(configuration:$config)))->project(new Term('object', 'box', secret:true), ['keep'], $state, 'example.cache');
        self::assertSame('selected', $value->native());
        self::assertTrue($value->isSecret());
    }

    public function testProjectRetainsUnknownDomainAndMissingFieldDependencies(): void
    {
        $collector = new ObservationCollector(\Tests\Fake\SolverFixture::context());
        $domain = new Term('domain', 'missing.domain', secret:true);
        $unknown = $collector->project($domain, ['field'], new State());
        $array = Term::array(['public' => Term::constant(1)]);
        $absent = $collector->project($array, ['absent'], new State());
        self::assertSame('projection', $unknown->kind);
        self::assertSame([$domain], $unknown->operands);
        self::assertTrue($unknown->isSecret());
        self::assertSame('array-read', $absent->kind);
        self::assertSame($array, $absent->operands[0]);
        self::assertSame('absent', $absent->operands[1]->native());
    }

    public function testProjectDelegatesRegisteredDomainProjectionWithoutErasingConfidentiality(): void
    {
        $config = new Configuration(domains:[new \Tests\Fake\PolicyDomain()]);
        $input = new Term('domain', 'example.policy', ['representation' => Term::fromNative(['keep' => 'selected'])], secret:true);
        $value = (new ObservationCollector(\Tests\Fake\SolverFixture::context(configuration:$config)))->project($input, ['keep'], new State());
        self::assertSame('domain', $value->kind);
        self::assertSame('example.policy', $value->literal);
        self::assertSame('selected', $value->operands['representation']->native());
        self::assertTrue($value->isSecret());
    }

    /**
     * @param Query $query Requested observation
     * @param string $phase Executed phase
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnmatchedObservations')]
    public function testInstructionIgnoresDifferentOwnersRegistersPointsAndPhases(Query $query, string $phase): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $body = new CallableGraph('target', [], [], $source);
        $state = new State();
        $state->registers['r1'] = Term::constant(7);
        (new ObservationCollector($context))->instruction($body, new Instruction('i', 'constant', $source, 'r1'), $state, $phase);
        self::assertSame([], $context->normal);
        self::assertFalse($state->observed);
        self::assertSame('normal', $state->completion->kind);
    }

    /**
     * @return iterable<string,array{Query,string}>
     */
    public static function providerUnmatchedObservations(): iterable
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        yield 'value before execution' => [new ValueQuery(new ExpressionRef($source, 'target', 'r1')),'before'];
        yield 'value at invocation' => [new ValueQuery(new ExpressionRef($source, 'target', 'r1')),'invocation'];
        yield 'value different owner' => [new ValueQuery(new ExpressionRef($source, 'other', 'r1')),'after'];
        yield 'value different register' => [new ValueQuery(new ExpressionRef($source, 'target', 'r2')),'after'];
        yield 'state different owner' => [new StateQuery(new PointRef($source, 'other', 'i', 'before'), 'x'),'before'];
        yield 'state different instruction' => [new StateQuery(new PointRef($source, 'target', 'other', 'before'), 'x'),'before'];
        yield 'state different phase' => [new StateQuery(new PointRef($source, 'target', 'i', 'before'), 'x'),'after'];
        yield 'return is not an instruction observation' => [new ReturnQuery('target'),'after'];
    }

    /**
     * @param QueryScope $scope Requested invocation scope
     * @param string $completion Completion expected after an observation
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerObservationScopes')]
    public function testInstructionCapturesValueStateGuardAndEvidenceInTheRequestedScope(QueryScope $scope, string $completion): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $query = new ValueQuery(new ExpressionRef($source, 'target', 'r1'), scope:$scope);
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $state = new State();
        $state->registers['r1'] = Term::constant(7);
        $state->guard = ['branch' => true];
        $state->evidence = ['e1'];
        $state->memory->write($state->local('x'), Term::constant(3));
        (new ObservationCollector($context))->instruction(new CallableGraph('target', [], [], $source), new Instruction('i', 'constant', $source, 'r1'), $state, 'after');
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
     * @return iterable<string,array{QueryScope,string}>
     */
    public static function providerObservationScopes(): iterable
    {
        yield 'symbolic slice ends here' => [QueryScope::symbolic(),'observed'];
        yield 'entrypoint execution continues' => [QueryScope::fromEntrypoints([new EntryPoint('target')]),'normal'];
    }

    /**
     * @param Query $query Requested state or tuple
     * @param array<string,int> $expected Captured fields
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStateAndTupleQueries')]
    public function testInstructionCapturesStateAndTupleValuesAtTheMatchingPoint(Query $query, array $expected): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        $state->memory->write($state->local('x'), Term::fromNative(['field' => 3]));
        $state->memory->cells['ref'] = Term::constant(9);
        $state->registers['r1'] = Term::constant(7);
        $state->registers['r2'] = new Term('cell', 'ref');
        (new ObservationCollector($context))->instruction(new CallableGraph('target', [], [], $source), new Instruction('i', 'constant', $source, 'r1'), $state, 'before');
        self::assertCount(1, $context->normal);
        self::assertSame($expected, array_map(static fn (Term $value): int|float|string|bool|null => $value->literal, $context->normal[0]->values));
        self::assertSame(['field' => 3], $context->normal[0]->state['x']->native());
    }

    /**
     * @return iterable<string,array{Query,array<string,int>}>
     */
    public static function providerStateAndTupleQueries(): iterable
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $point = new PointRef($source, 'target', 'i', 'before');
        $qualified = new PointRef($source, '\\TARGET', 'i', 'before');
        yield 'qualified state' => [new StateQuery($qualified, 'x', new Projection(['field'])),['state' => 3]];
        yield 'qualified tuple' => [new TupleQuery($qualified, ['first' => new ExpressionRef($source, 'TARGET', 'r1')]),['first' => 7]];
        yield 'projected state' => [new StateQuery($point, 'x', new Projection(['field'])),['state' => 3]];
        yield 'correlated tuple' => [new TupleQuery($point, ['first' => new ExpressionRef($source, 'target', 'r1'),'second' => new ExpressionRef($source, 'target', 'r2')]),['first' => 7,'second' => 9]];
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
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $query = new ValueQuery(new ExpressionRef($source, 'target', 'r1'));
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $context->entrySymbol = $entry;
        $context->active = $active;
        $state = new State();
        $state->observed = $observed;
        $state->completion = new Completion($completion, new Term('throwable', 'Error'));
        (new ObservationCollector($context))->completion(new CallableGraph('target', [], [], $source), $state);
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
     * @param QueryScope $scope Invocation scope
     * @param int $active Recursive invocation count
     * @param int $outcomes Expected visible returns
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReturnCompletions')]
    public function testCompletionSelectsReturnSymbolsAndScopeWithoutDuplicatingRecursiveRoots(string $symbol, QueryScope $scope, int $active, int $outcomes): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $query = new ReturnQuery($symbol, $scope);
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $context->active['target'] = $active;
        $state = new State();
        $state->completion = new Completion('return', Term::constant(8));
        (new ObservationCollector($context))->completion(new CallableGraph('TaRgEt', [], [], new SourceRef('test', 'fixture.php', 0, 1)), $state);
        self::assertCount($outcomes, $context->normal);
        self::assertSame([], $context->exceptional);
    }

    /**
     * @return iterable<string,array{string,QueryScope,int,int}>
     */
    public static function providerReturnCompletions(): iterable
    {
        yield 'case insensitive match' => ['TARGET',QueryScope::symbolic(),1,1];
        yield 'other callable' => ['other',QueryScope::symbolic(),1,0];
        yield 'recursive root suppressed' => ['target',QueryScope::symbolic(),2,0];
        yield 'entrypoint nested return' => ['target',QueryScope::fromEntrypoints([new EntryPoint('target')]),2,1];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNamedOwnerVariants')]
    public function testInstructionNormalizesNamedOwnersBeforeCapturingValues(string $owner): void
    {
        $fixture = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $query = new ValueQuery(new ExpressionRef($source, $owner, 'r1'));
        $context = new Context($fixture->program, $query, $fixture->configuration, $fixture->models);
        $state = new State();
        $state->registers['r1'] = Term::constant(7);
        (new ObservationCollector($context))->instruction(new CallableGraph('N\\target', [], [], $source), new Instruction('i', 'constant', $source, 'r1'), $state, 'after');
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
    public function testRepeatedRecognizesAControlFlowCycle(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('s', 'test.php', 0, 1);
        $body = new CallableGraph('target', [], [new \Deriver\ControlFlow\BasicBlock(0, [], new \Deriver\ControlFlow\Terminator('jump', targets: [0]))], $source);
        self::assertTrue((new ObservationCollector($context))->repeated($body, 0));
    }

}
