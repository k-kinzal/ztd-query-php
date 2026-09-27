<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call;

use Deriver\Api\Project\Configuration;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Memory\Location;
use Deriver\Internal\Solver\Call\MethodInvocation;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchProvider;
use Deriver\Model\Provider\DispatchTarget;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(Configuration::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(Location::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\Closure\Binding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Closure\Capture::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ProviderDispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
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
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(DispatchTarget::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Standard\ScalarFunctions::class)]
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
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([1,2], array_map(static fn (\Deriver\Api\Result\Alternative $a): int|float|string|bool|null => $a->values['return']->literal, $result->normalOutcomes));
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
