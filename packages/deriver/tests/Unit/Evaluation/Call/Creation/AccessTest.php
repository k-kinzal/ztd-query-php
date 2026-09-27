<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Creation;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\IntrinsicTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Model\CallDescription;
use Deriver\Model\Compilation\PlanActions;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanFootprints;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Exceptional;
use Deriver\Result\Frontier;
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
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
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
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(PlanActions::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(PlanFootprints::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(Signature::class)]
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
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Exceptional::class)]
#[UsesClass(Frontier::class)]
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
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
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
