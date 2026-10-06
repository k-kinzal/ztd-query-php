<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchProvider;
use Deriver\Model\Provider\DispatchTarget;
use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use Deriver\Result\Alternative;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
#[UsesClass(\Deriver\Model\Registration\SignatureIdentity::class)]
#[UsesClass(\Deriver\Model\Registration\ModelPrecedence::class)]
#[UsesClass(\Deriver\Model\Registration\Declarations::class)]
#[UsesClass(\Deriver\Evaluation\Call\SymbolicEnums::class)]
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
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Closure\Binding::class)]
#[UsesClass(\Deriver\Evaluation\Call\Closure\Capture::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\NativeArguments::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
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
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
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
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Builtin\ScalarFunctions::class)]
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
#[UsesClass(DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(DispatchTarget::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
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
#[UsesClass(ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(Alternative::class)]
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
#[UsesClass(Term::class)]
#[Small]
final class MethodInvocationTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{public $value="first";function __clone(){$this->value="clone";}}function target(){$a=new Box;$b=clone $a;return [$a->value,$b->value];}');
        self::assertSame(['first', 'clone'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCandidatesRetainsEachClosedWorldImplementation(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php interface I{function run();}final class A implements I{function run(){return 1;}}final class B implements I{function run(){return 2;}}function target(I $value){return $value->run();}', new Configuration(closedWorld:true));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([1,2], array_map(static fn (Alternative $a): int|float|string|bool|null => $a->values['return']->literal, $result->normalOutcomes));
    }
    public function testReceiverPreservesForwardedLateStaticCalls(): void
    {
        $state = new State();
        $state->lateStaticClass = 'Child';
        $invocation = new MethodInvocation(new Machine(SolverFixture::context()));
        self::assertNull($invocation->receiver(Term::constant('self'), $state, 'ParentClass', true));
        $invocation->receiver(Term::constant('Other'), $state, 'Other', true);
        self::assertSame('Child', $state->lateStaticClass);
    }
    public function testCalledClassForwardsRelativeNamesWithoutChangingCallerState(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Machine($context);
        $state = new State();
        $instruction = new Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $state->lateStaticClass = 'Child';
        $invocation = new MethodInvocation($machine);
        self::assertSame('Child', $invocation->calledClass(Term::constant('self'), $state, 'Base', true));
        self::assertSame('Other', $invocation->calledClass(Term::constant('Other'), $state, 'Other', true));
        self::assertSame('Child', $state->lateStaticClass);
    }
    /**
     * @param Term $name Unknown method spelling
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnknownNames')]
    public function testApplyRetainsUnresolvedMethodNamesAndBothCompletions(Term $name): void
    {
        $context = SolverFixture::context();
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->registers = ['receiver' => Term::parameter('object', 'object'),'name' => $name];
        $paths = (new MethodInvocation(new Machine($context)))->apply($caller, new Instruction('call', 'invoke-method', $caller->source, 'result', ['receiver','name']), $state, []);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerUnknownNames(): array
    {
        return ['symbolic' => [Term::parameter('method', 'string')],'nonstring' => [Term::constant(1)]];
    }

    /**
     * @param Term $receiver Invalid instance receiver
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidReceivers')]
    public function testApplyRaisesAnErrorForConcreteNonObjectReceivers(Term $receiver): void
    {
        $context = SolverFixture::context();
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->registers = ['receiver' => $receiver,'name' => Term::constant('run')];
        $paths = (new MethodInvocation(new Machine($context)))->apply($caller, new Instruction('call', 'invoke-method', $caller->source, 'result', ['receiver','name']), $state, []);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerInvalidReceivers(): array
    {
        return ['integer' => [Term::constant(1)],'array' => [Term::array([])],'null' => [Term::constant(null)]];
    }

    public function testCandidatesKeepsApplicableProviderTargetsAndTheirReceiverOverrides(): void
    {
        $context = SolverFixture::context('<?php class Impl{public int $value;function run(){return $this->value;}}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $original = new Term('object', 'original', attributes:['class' => 'Impl']);
        $replacement = new Term('object', 'replacement', attributes:['class' => 'Impl']);
        $state->memory->write(new Location('object:original', ['value']), Term::constant(1));
        $state->memory->write(new Location('object:replacement', ['value']), Term::constant(7));
        $decision = new DispatchDecision([
            new DispatchTarget('missing', condition:Term::constant(false)),
            new DispatchTarget('Impl::run', $replacement),
            new DispatchTarget('Impl::run', condition:Term::constant(true)),
        ], true);
        $paths = (new MethodInvocation(new Machine($context)))->candidates($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), $state, [], $original, 'Service', 'run', $decision);
        self::assertCount(2, $paths);
        self::assertSame([7,1], array_map(static fn (State $path): int|float|string|bool|null => $path->registers['result']->literal, $paths));
        self::assertSame(['normal','normal'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame([], $context->frontiers);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }

    /**
     * @param bool $closedWorld Closed project assumption
     * @param bool $exhaustive Provider completeness
     * @param string $modifier Captured class finality
     * @param int $expectedCount Source result plus any open normal and exceptional paths
     * @param list<string> $codes Expected boundaries
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerWorldBounds')]
    public function testCandidatesUsesOnlyExplicitCompletenessBounds(bool $closedWorld, bool $exhaustive, string $modifier, int $expectedCount, array $codes): void
    {
        $context = SolverFixture::context('<?php '.$modifier.' class Box{function run(){return 1;}}function target(){}', configuration:new Configuration(closedWorld:$closedWorld));
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $paths = (new MethodInvocation(new Machine($context)))->candidates($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [], Term::parameter('box', 'Box'), 'BoX', 'run', new DispatchDecision(exhaustive:$exhaustive));
        self::assertCount($expectedCount, $paths);
        self::assertSame(1, $paths[0]->registers['result']->native());
        self::assertSame($codes, array_column($context->frontiers, 'code'));
    }

    /**
     * @return array<string,array{bool,bool,string,int,list<string>}>
     */
    public static function providerWorldBounds(): array
    {
        return ['open' => [false,false,'',3,['OPEN_DISPATCH']],'closed project' => [true,false,'',1,[]],'complete provider' => [false,true,'',1,[]],'final declaration' => [false,false,'final',1,[]]];
    }

    /**
     * @param string $operation Method syntax
     * @param Term $receiver Concrete or unresolved runtime receiver
     * @param int $expected Selected source or provider result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerProviderSelection')]
    public function testApplyUsesProvidersForUnresolvedTargetsAndKeepsKnownSourceTargets(string $operation, Term $receiver, int $expected): void
    {
        $provider = self::createStub(DispatchProvider::class);
        $provider->method('id')->willReturn('example.dispatch');
        $provider->method('version')->willReturn('1');
        $provider->method('resolve')->willReturn(new DispatchDecision([new DispatchTarget('provided')], true));
        $context = SolverFixture::context('<?php class Box{static function run(){return 1;}}function provided(){return 2;}function target(){}', configuration:new Configuration(providers:[$provider]));
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->registers = ['receiver' => $receiver,'name' => Term::constant('run')];
        $paths = (new MethodInvocation(new Machine($context)))->apply($caller, new Instruction('call', $operation, $caller->source, 'result', ['receiver','name']), $state, []);
        self::assertCount(1, $paths);
        self::assertSame($expected, $paths[0]->registers['result']->native());
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return array<string,array{string,Term,int}>
     */
    public static function providerProviderSelection(): array
    {
        return [
            'known static' => ['invoke-static',Term::constant('Box'),1],
            'known object' => ['invoke-method',new Term('object', 'box', attributes:['class' => 'Box']),1],
            'unknown static' => ['invoke-static',Term::constant('Missing'),2],
            'unknown object' => ['invoke-method',new Term('object', 'box', attributes:['class' => 'Missing']),2],
            'symbolic receiver' => ['invoke-method',Term::parameter('receiver', 'Missing'),2],
        ];
    }

    public function testCandidatesKeepsAnUnresolvedBoundaryWhenNoFeasibleImplementationExists(): void
    {
        $context = SolverFixture::context(configuration:new Configuration(closedWorld:true));
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $paths = (new MethodInvocation(new Machine($context)))->candidates($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [], Term::parameter('receiver', 'Missing'), 'Missing', 'run', new DispatchDecision([new DispatchTarget('missing', condition:Term::constant(false))], true));
        self::assertCount(2, $paths);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testModeledInvokesUserModelsOnATypeBoundWithoutSource(): void
    {
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example.missing-run', '1', 'Missing::run'), new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(Term::constant(7)))]));
        $session = \Tests\Fake\Analysis::session('<?php function target(?Missing $value){return $value->run();}', new Configuration(closedWorld:true, models:[$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([7], array_map(static fn (Alternative $a): int|float|string|bool|null => $a->values['return']->literal, $result->normalOutcomes));
        self::assertContains('Error', array_map(static fn (\Deriver\Result\Exceptional $e): mixed => $e->exception->literal, $result->exceptionalOutcomes));
        self::assertSame([], $result->frontiers);
    }

    public function testModeledLeavesSelectedSourceImplementationsAndUnmodeledTypesToDispatch(): void
    {
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example.missing-run', '1', 'Missing::run'), new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(Term::constant(7)))]));
        $context = SolverFixture::context(configuration:new Configuration(models:[$model]));
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $invocation = new MethodInvocation(new Machine($context));
        $instruction = new Instruction('call', 'invoke-method', $caller->source, 'result');
        self::assertSame([], $invocation->modeled($caller, $instruction, new State(), [], Term::parameter('receiver', 'Missing'), 'Missing', 'run', ['Missing' => 'Missing::run']));
        self::assertSame([], $invocation->modeled($caller, $instruction, new State(), [], Term::parameter('receiver', 'Other'), 'Other|null', 'run', []));
    }

    public function testReceiverBindsTheCurrentInstanceForStaticSyntaxOnlyWhenOneExists(): void
    {
        $invocation = new MethodInvocation(new Machine(SolverFixture::context()));
        $state = new State();
        $receiver = new Term('object', 'explicit', attributes:['class' => 'Box']);
        $current = new Term('object', 'current', attributes:['class' => 'Box']);
        $state->memory->write($state->local('this'), $current);
        self::assertSame($receiver, $invocation->receiver($receiver, $state, 'Box', false));
        self::assertSame($current, $invocation->receiver(Term::constant('self'), $state, 'Box', true));
        self::assertNull($invocation->receiver(Term::constant('Box'), new State(), 'Box', true));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdentities')]
    public function testIdentityKeepsOnlyStableReceiverSpellings(Term $receiver, ?string $expected): void
    {
        $state = new State();
        $invocation = new MethodInvocation(new Machine(SolverFixture::context()));
        $first = $invocation->identity($receiver, $state);
        $second = $invocation->identity($receiver, $state);
        self::assertSame($expected ?? 'parameter-object:0', $first);
        self::assertSame($expected ?? 'parameter-object:1', $second);
    }

    /**
     * @return iterable<string, array{Term, string|null}>
     */
    public static function providerIdentities(): iterable
    {
        yield 'parameter' => [Term::parameter('repo', 'Repo'), 'repo'];
        yield 'external input' => [new Term('external', 'object:this:Ctl::repo', attributes:['type' => 'Repo']), 'object:this:Ctl::repo'];
        yield 'stable property read' => [new Term('opaque', 'object:this:Ctl::repo', attributes:['type' => 'Repo', 'stability' => 'state']), 'object:this:Ctl::repo'];
        yield 'object' => [new Term('object', 'object:1', attributes:['class' => 'Repo']), 'object:1'];
        yield 'residual reason' => [Term::opaque('OPEN_DISPATCH', 'Repo'), null];
        yield 'unnamed expression' => [new Term('binary', null, attributes:['type' => 'Repo']), null];
    }

    /**
     * @param Term $receiver Spelled target
     * @param bool $static Invocation syntax
     * @param string $expected Called class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCalledClasses')]
    public function testCalledClassForwardsEveryRelativeSpellingWithoutRebindingInstanceCalls(Term $receiver, bool $static, string $expected): void
    {
        $state = new State();
        $state->lateStaticClass = 'Child';
        self::assertSame($expected, (new MethodInvocation(new Machine(SolverFixture::context())))->calledClass($receiver, $state, 'Resolved', $static));
    }

    /**
     * @return array<string,array{Term,bool,string}>
     */
    public static function providerCalledClasses(): array
    {
        return ['self' => [Term::constant('SELF'),true,'Child'],'parent' => [Term::constant('PARENT'),true,'Child'],'static' => [Term::constant('STATIC'),true,'Child'],'explicit' => [Term::constant('Box'),true,'Resolved'],'instance' => [Term::constant('self'),false,'Resolved'],'symbolic class' => [new Term('opaque'),true,'Resolved']];
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProviderExternal(\Tests\Fake\Programs\DynamicClassPrograms::class, 'methods')]
    public function testApplySeparatesLiteralAndRuntimeClassOperands(string $source, string $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->projectDiagnostics);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags:JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }
}
