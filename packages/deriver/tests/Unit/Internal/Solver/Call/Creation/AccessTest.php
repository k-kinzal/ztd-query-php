<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Creation;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\Creation\Access;
use Deriver\Internal\Solver\Call\PassedArgument;
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

/**
 * @covers \Deriver\Internal\Solver\Call\Creation\Access
 */
#[CoversClass(Access::class)]
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
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(PassedArgument::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
        $body = new CallableIR('Base::factory', [], [], SummaryFixture::body($context)->source, className:'Base');
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
        $body = new CallableIR('caller', [], [], SummaryFixture::body($context)->source, className:$scope);
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
}
