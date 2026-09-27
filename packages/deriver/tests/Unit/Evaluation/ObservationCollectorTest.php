<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Analysis\CallObservations;
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
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Contract\DomainLaws;
use Deriver\Model\Domain\DomainFact;
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
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\Observation;
use Deriver\Reference\PointRef;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Exceptional;
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
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\CallSiteIndex;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\Operations;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObservationCollector::class)]
#[UsesClass(CallObservations::class)]
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
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Library::class)]
#[UsesClass(DomainLaws::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(\Deriver\Model\Domain\DomainOperations::class)]
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
#[UsesClass(StateQuery::class)]
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
#[UsesClass(Observation::class)]
#[UsesClass(PointRef::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Exceptional::class)]
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
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallSiteIndex::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(Operations::class)]
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
}
