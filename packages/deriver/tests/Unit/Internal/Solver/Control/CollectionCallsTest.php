<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Control\CollectionCalls;
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

#[CoversClass(CollectionCalls::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(CollectionCalls::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Internal\Value\SecretFingerprint::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\CallModel::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(Term::class)]
#[Small]
final class CollectionCallsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_reduce([1,2,3],fn($a,$b)=>$a+$b,0);}');
        self::assertSame(6, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testElementEvaluatesCallbackEffectsInIterationOrder(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$sum=0;$values=array_map(function($n)use(&$sum){$sum+=$n;return $sum;},[2,3]);return [$values,$sum];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([[2,5],5], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFilterRetainsOriginalKeysForAcceptedElements(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_filter(["a"=>1,"b"=>2],fn($x)=>$x>1);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(['b' => 2], $result->normalOutcomes[0]->values['return']->native());
    }
    public function testKnownCallbackDistinguishesNullFromUnresolvedClassLookup(): void
    {
        $calls = new CollectionCalls(new Machine(SolverFixture::context()));
        self::assertTrue($calls->knownCallback(Term::constant(null)));
        self::assertTrue($calls->knownCallback(Term::constant('strlen')));
        self::assertFalse($calls->knownCallback(Term::constant('ExternalClass::method')));
    }
    public function testKnownModeRejectsSymbolicFilterArgumentSelection(): void
    {
        $calls = new CollectionCalls(new Machine(SolverFixture::context()));
        self::assertFalse($calls->knownMode('array_filter', [Term::array([]), Term::constant(null), Term::parameter('mode', 'int')]));
        self::assertTrue($calls->knownMode('array_filter', []));
        self::assertTrue($calls->knownMode('array_map', []));
    }

    /**
     * @param string $source Trusted fixture source
     * @param string $expectedJson Expected observable value and alias effects
     * @throws JsonException If expected values cannot be decoded
     */
    #[DataProvider('providerCollectionPrograms')]
    public function testApplyPreservesKeysReferencesAndOrderedCallbackEffects(string $source, string $expectedJson): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerCollectionPrograms(): array
    {
        return \Tests\Fake\Programs\CollectionPrograms::cases();
    }

    public function testFilterKeepsCorrelatedMembershipAndIndependentStorage(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['accumulator'] = Term::fromNative(['previous' => 7]);
        $state->memory->write($state->local('value'), Term::constant(1));
        $element = Term::constant('selected');
        $paths = (new CollectionCalls(new Machine($context)))->filter($state, Term::parameter('predicate', 'bool'), $element, 'key', 'accumulator');
        self::assertCount(2, $paths);
        self::assertSame(['previous' => 7,'key' => 'selected'], $paths[0]->registers['accumulator']->native());
        self::assertSame(['previous' => 7], $paths[1]->registers['accumulator']->native());
        self::assertNotSame($paths[0]->guard, $paths[1]->guard);
        $paths[0]->memory->write($paths[0]->local('value'), Term::constant(2));
        self::assertSame(1, $paths[1]->snapshot()['value']->native());
        self::assertSame(['previous' => 7], $state->registers['accumulator']->native());
    }

    public function testFilterRetainsPredicateConfidentialityOnBothMembershipOutcomes(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['accumulator'] = Term::array([]);
        $predicate = new Term('parameter', 'predicate', attributes:['type' => 'bool'], secret:true);
        $paths = (new CollectionCalls(new Machine($context)))->filter($state, $predicate, Term::constant('selected'), 'key', 'accumulator');
        self::assertCount(2, $paths);
        self::assertTrue($paths[0]->registers['accumulator']->isSecret());
        self::assertTrue($paths[1]->registers['accumulator']->isSecret());
    }

    public function testFilterRetainsExistingAggregateConfidentialityAfterPublicDecisions(): void
    {
        $context = SolverFixture::context();
        $state = new State();
        $state->registers['accumulator'] = new Term('array', operands:['previous' => Term::constant(7)], attributes:['open' => false], secret:true);
        $paths = (new CollectionCalls(new Machine($context)))->filter($state, Term::constant(true), Term::constant('selected'), 'key', 'accumulator');
        self::assertCount(1, $paths);
        self::assertTrue($paths[0]->registers['accumulator']->isSecret());
        self::assertSame(['previous' => 7,'key' => 'selected'], $paths[0]->registers['accumulator']->native());
    }

    /**
     * @param string $name Collection operation
     * @param list<Term> $values Bound inputs requiring an explicit boundary
     */
    #[DataProvider('providerUnsupportedCollections')]
    public function testApplyKeepsUnknownCollectionEffectsAndBothExits(string $name, array $values): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->memory->cells['global:value'] = Term::constant(7);
        $paths = (new CollectionCalls(new Machine($context)))->apply($body, new Instruction('call', 'intrinsic', $body->source, 'result', name:$name), $state, $values);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $paths[0]->registers['result']->literal);
        self::assertSame('opaque', $paths[0]->memory->cells['global:value']->kind);
        self::assertSame(['UNSUPPORTED_MODEL_CASE'], array_column($context->frontiers, 'code'));
    }

    /**
     * @return iterable<string,array{string,list<Term>}>
     */
    public static function providerUnsupportedCollections(): iterable
    {
        yield 'unknown callback' => ['array_map',[Term::parameter('callback'),Term::fromNative([1])]];
        yield 'unknown array' => ['array_map',[Term::constant(null),Term::parameter('array', 'array')]];
        yield 'open array' => ['array_map',[Term::constant(null),Term::array([], true)]];
        yield 'multiple arrays' => ['array_map',[Term::constant(null),Term::fromNative([1]),Term::fromNative([[2]])]];
        yield 'unknown filter mode' => ['array_filter',[Term::fromNative([1]),Term::constant(null),Term::parameter('mode', 'int')]];
    }

    public function testElementPassesConfidentialAggregateKeysToKeyCallbacks(): void
    {
        $context = SolverFixture::context('<?php function callback($key){return $key;}function target(){}');
        $body = SummaryFixture::body($context);
        $state = new State();
        $array = new Term('array', operands:['key' => Term::constant(7)], attributes:['open' => false], secret:true);
        $state->registers['accumulator'] = Term::array([]);
        $values = [$array,Term::constant('callback'),Term::constant(2)];
        $paths = (new CollectionCalls(new Machine($context)))->element($body, new Instruction('call', 'intrinsic', $body->source, 'result', name:'array_filter'), $state, $values[1], $array->operands['key'], 'key', 'accumulator', $values);
        self::assertCount(1, $paths);
        self::assertTrue($paths[0]->registers['result']->isSecret());
        self::assertSame('key', $paths[0]->registers['result']->native());
        self::assertTrue($paths[0]->registers['accumulator']->isSecret());
    }

    public function testCompletePublishesConfidentialResultsWithoutReplacingExceptions(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $normal = new State();
        $normal->registers['accumulator'] = Term::fromNative(['value' => 7]);
        $exceptional = new State();
        $failure = new \Deriver\Internal\Solver\Completion('throw', new Term('throwable', 'Error'));
        $exceptional->completion = $failure;
        $paths = (new CollectionCalls(new Machine($context)))->complete(new Instruction('call', 'intrinsic', $body->source, 'result'), [$normal,$exceptional], [Term::constant('private', true)], 'accumulator');
        self::assertCount(2, $paths);
        self::assertSame(['value' => 7], $paths[0]->registers['result']->native());
        self::assertTrue($paths[0]->registers['result']->isSecret());
        self::assertArrayNotHasKey('accumulator', $paths[0]->registers);
        self::assertSame($failure, $paths[1]->completion);
        self::assertArrayNotHasKey('result', $paths[1]->registers);
    }

    public function testCompleteRetainsTheAccumulatorWhenInputsArePublic(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $value = new Term('array', attributes:['open' => false], secret:true);
        $state->registers['accumulator'] = $value;
        $paths = (new CollectionCalls(new Machine($context)))->complete(new Instruction('call', 'intrinsic', $body->source, 'result'), [$state], [Term::constant('public')], 'accumulator');
        self::assertSame($value, $paths[0]->registers['result']);
        self::assertArrayNotHasKey('accumulator', $paths[0]->registers);
    }
}
