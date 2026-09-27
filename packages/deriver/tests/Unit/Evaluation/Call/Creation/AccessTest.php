<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Creation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\PassedArgument;
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

/**
 * @covers \Deriver\Evaluation\Call\Creation\Access
 */
#[CoversClass(Access::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
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
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
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
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
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
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
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
final class AccessTest extends TestCase
{
    public function testNameResolvesKnownObjectAndLateStaticClass(): void
    {
        $program = SolverFixture::context('<?php class B{function f(){}}');
        $access = new Access(new Machine($program));
        $caller = $program->program->callable('B::f');
        self::assertNotNull($caller);
        $state = new State();
        $state->lateStaticClass = 'B';
        self::assertSame('B', $access->name(Term::constant('static'), $caller, $state));
        self::assertSame('B', $access->name(new Term('object', 'o', attributes:['class' => 'b']), $caller, $state));
        self::assertNull($access->name(Term::parameter('class'), $caller, $state));
    }
    /**
     * @throws JsonException If captured source metadata cannot be encoded
     */
    public function testCheckKeepsUnknownAllocationEffectsOpen(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=1;$b=new Unavailable($x);return $b;}');
        self::assertSame('INCOMPLETE_SOURCE', $result->frontiers[0]->code);
        self::assertNotEmpty($result->normalOutcomes);
        self::assertNotEmpty($result->exceptionalOutcomes);
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
    }
    /**
     * @throws JsonException If captured source metadata cannot be encoded
     */
    public function testLifecycleRejectsAbstractConstructionBeforeBodyEffects(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php abstract class B{function __construct(&$x){$x=9;}}function target(){$x=1;try{new B($x);}catch(Error $e){return $x;}return 999;}');
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    /**
     * @throws JsonException If captured source metadata cannot be encoded
     */
    public function testWithoutConstructorRejectsNamedActuals(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{}function target(){try{new B(x:1);}catch(Error $e){return "named";}return 999;}');
        self::assertSame('named', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * @param Term $input Class expression
     * @param string|null $expected Canonical runtime class
     */
    #[DataProvider('providerClassExpressions')]
    public function testNameUsesRuntimeClassIdentityAndLexicalRelativeNames(Term $input, ?string $expected): void
    {
        $context = SolverFixture::context('<?php class Ancestor{}class Base extends Ancestor{}class Child extends Base{}function target(){}');
        $body = new CallableGraph('Base::factory', [], [], SummaryFixture::body($context)->source, className:'Base');
        $state = new State();
        $state->lateStaticClass = 'Child';
        self::assertSame($expected, (new Access(new Machine($context)))->name($input, $body, $state));
    }

    /**
     * @return iterable<string,array{Term,string|null}>
     */
    public static function providerClassExpressions(): iterable
    {
        yield 'case insensitive source' => [Term::constant('bAsE'),'Base'];
        yield 'self' => [Term::constant('self'),'Base'];
        yield 'parent' => [Term::constant('parent'),'Ancestor'];
        yield 'late static' => [Term::constant('static'),'Child'];
        yield 'builtin' => [Term::constant('runtimeexception'),'RuntimeException'];
        yield 'missing source' => [Term::constant('External'),'External'];
        yield 'object runtime class' => [new Term('object', 'identity', attributes:['class' => 'child']),'Child'];
        yield 'enum runtime class' => [new Term('enum', 'Some::Case', attributes:['class' => 'Some']),'Some'];
        yield 'object without known class' => [new Term('object', 'identity'),null];
        yield 'integer' => [Term::constant(4),null];
        yield 'boolean' => [Term::constant(false),null];
        yield 'symbolic string' => [Term::parameter('class', 'string'),null];
        yield 'array' => [Term::array([]),null];
    }

    /**
     * @param string $declaration Class declaration
     * @param string $class Runtime class
     * @param string $scope Lexical caller
     * @param bool $clone Lifecycle mode
     * @param bool $allowed Expected permission
     */
    #[DataProvider('providerLifecycleAccess')]
    public function testLifecycleChecksInstantiationAndMethodVisibilityBeforeEffects(string $declaration, string $class, string $scope, bool $clone, bool $allowed): void
    {
        $context = SolverFixture::context('<?php '.$declaration.' function target(){}');
        $body = new CallableGraph('caller', [], [], SummaryFixture::body($context)->source, className:$scope);
        $state = new State();
        $state->memory->write($state->local('unchanged'), Term::constant(7));
        $paths = (new Access(new Machine($context)))->lifecycle($class, $body, $state, $clone);
        self::assertSame($allowed, $paths === null);
        self::assertSame($allowed ? null : 'Error', $paths[0]->completion->value->literal ?? null);
        self::assertSame(7, $state->snapshot()['unchanged']->native());
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{string,string,string,bool,bool}>
     */
    public static function providerLifecycleAccess(): iterable
    {
        yield 'ordinary class' => ['class Base{}','Base','',false,true];
        yield 'abstract class' => ['abstract class Base{}','Base','',false,false];
        yield 'interface' => ['interface Base{}','Base','',false,false];
        yield 'trait' => ['trait Base{}','Base','',false,false];
        yield 'enum' => ['enum Base{case One;}','Base','',false,false];
        yield 'Throwable interface' => ['','Throwable','',false,false];
        yield 'public constructor' => ['class Base{public function __construct(){}}','Base','',false,true];
        yield 'private external constructor' => ['class Base{private function __construct(){}}','Base','',false,false];
        yield 'private same scope constructor' => ['class Base{private function __construct(){}}','Base','Base',false,true];
        yield 'private child scope constructor' => ['class Base{private function __construct(){}}class Child extends Base{}','Child','Child',false,false];
        yield 'protected external constructor' => ['class Base{protected function __construct(){}}','Base','',false,false];
        yield 'protected child scope constructor' => ['class Base{protected function __construct(){}}class Child extends Base{}','Child','Child',false,true];
        yield 'private external clone' => ['class Base{private function __clone(){}}','Base','',true,false];
        yield 'private same scope clone' => ['class Base{private function __clone(){}}','Base','Base',true,true];
        yield 'clone ignores constructor access' => ['class Base{private function __construct(){}public function __clone(){}}','Base','',true,true];
        yield 'construction ignores clone access' => ['class Base{public function __construct(){}private function __clone(){}}','Base','',false,true];
        yield 'protected external clone' => ['class Base{protected function __clone(){}}','Base','',true,false];
        yield 'protected child clone' => ['class Base{protected function __clone(){}}class Child extends Base{}','Child','Child',true,true];
    }

    /**
     * @param string $operation Allocation form
     * @param Term $input Invalid runtime operand
     */
    #[DataProvider('providerInvalidAllocationOperands')]
    public function testCheckRejectsInvalidOperandsWithoutAllocatingOrChangingLocals(string $operation, Term $input): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->registers['input'] = $input;
        $state->memory->write($state->local('unchanged'), Term::constant(7));
        $paths = (new Access(new Machine($context)))->check($body, new Instruction('new', $operation, $body->source, 'result', ['input']), $state, []);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame(7, $paths[0]->snapshot()['unchanged']->native());
        self::assertSame([], $paths[0]->memory->classes);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{string,Term}>
     */
    public static function providerInvalidAllocationOperands(): iterable
    {
        yield 'new integer' => ['new',Term::constant(3)];
        yield 'new null' => ['new',Term::constant(null)];
        yield 'new boolean' => ['new',Term::constant(false)];
        yield 'new array' => ['new',Term::array([])];
        yield 'clone string' => ['clone',Term::constant('stdClass')];
        yield 'clone integer' => ['clone',Term::constant(3)];
        yield 'clone array' => ['clone',Term::array([])];
        yield 'clone enum' => ['clone',new Term('enum', 'Mode::One', attributes:['class' => 'Mode'])];
        yield 'clone native exception' => ['clone',new Term('object', 'error', attributes:['class' => 'RuntimeException'])];
    }

    public function testCheckRetainsOpenDispatchEffectsForASymbolicClass(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $state->registers['class'] = Term::parameter('class', 'string');
        $state->memory->cells['global:shared'] = Term::constant(7);
        $paths = (new Access(new Machine($context)))->check($body, new Instruction('new', 'new', $body->source, 'result', ['class']), $state, []);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('OPEN_DISPATCH', $paths[0]->registers['result']->literal);
        self::assertSame('opaque', $paths[0]->memory->cells['global:shared']->kind);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
    }

    public function testWithoutConstructorAcceptsUnusedPositionalArguments(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $source = Term::constant('stdClass');
        self::assertNull((new Access(new Machine($context)))->withoutConstructor($state, new Instruction('new', 'new', $body->source), [new PassedArgument(Term::constant(3)),new PassedArgument(Term::constant(4))], $source));
        self::assertSame('normal', $state->completion->kind);
    }

    public function testWithoutConstructorKeepsUnknownUnpackEffectsAndExceptions(): void
    {
        $context = SolverFixture::context();
        $body = SummaryFixture::body($context);
        $state = new State();
        $paths = (new Access(new Machine($context)))->withoutConstructor($state, new Instruction('new', 'new', $body->source, 'result'), [new PassedArgument(Term::parameter('arguments', 'array'), '*')], Term::constant('stdClass'));
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $paths[0]->registers['result']->literal);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProviderExternal(\Tests\Fake\Programs\DynamicClassPrograms::class, 'allocations')]
    public function testNameSeparatesLiteralAndRuntimeClassOperands(string $source, string $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->projectDiagnostics);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags:JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    #[DataProvider('providerAllocationOperands')]
    public function testInvalidOperandDistinguishesAllocationFromCloning(Term $value, bool $clone, bool $invalid): void
    {
        $access = new Access(new Machine(SolverFixture::context()));
        self::assertSame($invalid, $access->invalidOperand($value, $clone));
    }

    /**
     * @return iterable<string,array{Term,bool,bool}>
     */
    public static function providerAllocationOperands(): iterable
    {
        yield 'new class string' => [Term::constant('Box'),false,false];
        yield 'clone class string' => [Term::constant('Box'),true,true];
        yield 'new number' => [Term::constant(1),false,true];
        yield 'clone number' => [Term::constant(1),true,true];
        yield 'new null' => [Term::constant(null),false,true];
        yield 'clone null' => [Term::constant(null),true,true];
        yield 'new array' => [Term::array([]),false,true];
        yield 'clone array' => [Term::array([]),true,true];
        yield 'new object class' => [new Term('object', 'one'),false,false];
        yield 'clone object' => [new Term('object', 'one'),true,false];
        yield 'enum allocation needs lifecycle validation' => [new Term('enum', 'E::A'),false,false];
        yield 'enum cannot be cloned' => [new Term('enum', 'E::A'),true,true];
        yield 'unknown allocation remains unresolved' => [Term::parameter('x'),false,false];
        yield 'unknown clone remains unresolved' => [Term::parameter('x'),true,false];
    }
}
