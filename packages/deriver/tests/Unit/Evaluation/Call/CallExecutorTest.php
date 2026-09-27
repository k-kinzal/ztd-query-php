<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(CallExecutor::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Closure\Binding::class)]
#[UsesClass(\Deriver\Evaluation\Call\Closure\Capture::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
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
#[UsesClass(\Deriver\Evaluation\Operation\CallablePredicate::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyReference::class)]
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
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Builtin\ScalarFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\StringFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\TypePredicates::class)]
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
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
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
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class CallExecutorTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInstructionPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function tagged(string $tag,int $id) { return $tag . ":" . $id; } function target() { return [tagged("user",1),tagged("item",2)]; }');
        self::assertSame(['user:1', 'item:2'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInvokeBindsCapturedClosureValues(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=2;$f=fn($n)=>$x+$n;$x=9;return $f(3);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(5, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSymbolPropagatesACompletedReferenceWriteBackToTheCaller(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f(&$x){$x=2;return 3;}function target(){$x=1;$r=f($x);return [$x,$r];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([2,3], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObjectInvokesAnObjectCallable(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{function __invoke($x){return $x+1;}}function target(){$f=new B;return $f(3);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(4, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @param string $source Independently verified callable fixture
     * @param string $expectedJson Complete normal return observations
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCallableForms')]
    public function testInvokePreservesCallableBindingIdentityAndCallingContext(string $source, string $expectedJson): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($expected);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected[0], $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerCallableForms(): array
    {
        return array_map(static fn (array $fixture): array => [$fixture[0],$fixture[1]], \Tests\Fake\Programs\CallablePrograms::cases());
    }

    public function testInvokeUnwrapsAResolvedCallableAndPropagatesStrictArgumentErrors(): void
    {
        $context = SolverFixture::context('<?php declare(strict_types=1);function helper(int $x){return $x;}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $site = new Instruction('call', 'invoke', $caller->source, 'result');
        $target = new Term('callable', operands:[Term::constant('helper')]);
        $paths = (new CallExecutor(new Machine($context)))->invoke($target, [new PassedArgument(Term::constant('2'))], new State(), $site, $caller);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
    }

    public function testSymbolRestoresCallerLocalsAndKeepsEveryExitEffectAndExplanation(): void
    {
        $context = SolverFixture::context('<?php function helper(&$x,$flag){$x=2;if($flag){return 3;}$x=4;throw new Exception;}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->memory->write($state->local('callerValue'), Term::constant(1));
        $state->registers['old'] = Term::constant('preserved');
        $state->evidence = ['caller:first','caller:second'];
        $state->controls = ['control:first','control:second'];
        $state->guard = ['outer' => true];
        $state->constraints = ['input' => ['min' => 0,'max' => 10,'equal' => null,'excluded' => []]];
        $state->observed = true;
        $context->summaries->history = ['outer-call'];
        $site = new Instruction('call', 'invoke', $caller->source, 'result');
        $paths = (new CallExecutor(new Machine($context)))->symbol('helper', [new PassedArgument(Term::constant(1), location:$state->local('callerValue')),new PassedArgument(Term::parameter('flag', 'bool'))], $state, $site);
        self::assertCount(2, $paths);
        self::assertEqualsCanonicalizing(['normal','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertEqualsCanonicalizing([2,4], array_map(static fn (State $path): int|float|string|bool|null => $path->memory->read($path->local('callerValue'))->literal, $paths));
        self::assertSame(['callerValue'], array_keys($paths[0]->locals));
        self::assertSame('preserved', $paths[0]->registers['old']->native());
        self::assertTrue($paths[0]->observed);
        self::assertTrue($paths[1]->observed);
        self::assertTrue($paths[0]->guard['outer']);
        self::assertNotSame($paths[0]->guard, $paths[1]->guard);
        self::assertSame($state->constraints['input'], $paths[0]->constraints['input']);
        self::assertContains('caller:first', $paths[0]->evidence);
        self::assertContains('caller:second', $paths[0]->evidence);
        self::assertGreaterThan(2, count($paths[0]->evidence));
        self::assertSame(array_values(array_unique($paths[0]->evidence)), $paths[0]->evidence);
        self::assertContains('control:first', $paths[0]->controls);
        self::assertContains('control:second', $paths[0]->controls);
        self::assertSame(array_values(array_unique($paths[0]->controls)), $paths[0]->controls);
        self::assertSame(['outer-call'], $context->summaries->history);
        self::assertSame(1, $state->memory->read($state->local('callerValue'))->native());
        self::assertSame('normal', $state->completion->kind);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }

    public function testSymbolUsesWeakCoercionByDefaultAndOverridesOnlyCalleeLateStaticBinding(): void
    {
        $context = SolverFixture::context('<?php class Base{static function helper(int $x){return [$x,static::class,isset($this)];}}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->lateStaticClass = 'Caller';
        $receiver = new Term('object', 'box', attributes:['class' => 'Base']);
        $paths = (new CallExecutor(new Machine($context)))->symbol('Base::helper', [new PassedArgument(Term::constant('2'))], $state, new Instruction('call', 'invoke', $caller->source, 'result'), $receiver, calledClass:'Child');
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame([2,'Child',false], $paths[0]->registers['result']->native());
        self::assertSame('Caller', $paths[0]->lateStaticClass);
        self::assertSame('Caller', $state->lateStaticClass);
    }

    public function testObjectDispatchesReferencedArrayCallableElements(): void
    {
        $context = SolverFixture::context('<?php class Box{static function run($x){return $x+1;}}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $class = $state->memory->allocate(Term::constant('Box'));
        $method = $state->memory->allocate(Term::constant('run'));
        $target = Term::array([new Term('cell', $class->root),new Term('cell', $method->root)]);
        $paths = (new CallExecutor(new Machine($context)))->object($target, [new PassedArgument(Term::constant(2))], $state, new Instruction('call', 'invoke', $caller->source, 'result'), $caller);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame(3, $paths[0]->registers['result']->native());
    }

    /**
     * @param Term $target Value without a resolved object callable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNonObjectTargets')]
    public function testObjectLeavesOtherCallableFormsToTheirOwnResolution(Term $target): void
    {
        $context = SolverFixture::context('<?php class Box{}function target(){}');
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        self::assertNull((new CallExecutor(new Machine($context)))->object($target, [], new State(), new Instruction('call', 'invoke', $caller->source, 'result'), $caller));
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return array<string,array{Term}>
     */
    public static function providerNonObjectTargets(): array
    {
        return ['plain function' => [Term::constant('target')],'number' => [Term::constant(1)],'symbolic spelling' => [Term::parameter('Box::run')],'object without invoke' => [new Term('object', 'box', attributes:['class' => 'Box'])],'opaque' => [Term::opaque('unknown')]];
    }

    public function testInvokeRetainsAnOpenDispatchBoundaryForAnUnresolvedTarget(): void
    {
        $context = SolverFixture::context();
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $paths = (new CallExecutor(new Machine($context)))->invoke(Term::parameter('callback'), [], new State(), new Instruction('call', 'invoke', $caller->source, 'result'), $caller);
        self::assertCount(2, $paths);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
        self::assertSame('OPEN_DISPATCH', $paths[0]->registers['result']->literal);
        self::assertSame('throw', $paths[1]->completion->kind);
    }
}
