<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Control\CollectionCalls;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(Completion::class)]
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
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
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
#[UsesClass(\Deriver\Reference\SourceRef::class)]
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
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
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
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(\Deriver\Value\SecretFingerprint::class)]
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
        $failure = new Completion('throw', new Term('throwable', 'Error'));
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
