<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Member;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\Member\Invocation;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Internal\Solver\Call\Member\Invocation
 */
#[CoversClass(Invocation::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
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
#[UsesClass(SourceRef::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ConditionalLowering::class)]
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
#[UsesClass(CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ModelPrecedence::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(Term::class)]
#[Small]
final class InvocationTest extends TestCase
{
    /**
     * @throws JsonException If captured source metadata cannot be encoded
     */
    public function testCallRejectsAnInstanceMethodCalledStatically(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{function f(){return 1;}}function target(){try{return B::f();}catch(Error $e){return "access";}}');
        self::assertSame('access', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testInstanceRequiresTheRequestedClassRatherThanItsParent(): void
    {
        $invocation = new Invocation(new Machine(SolverFixture::context('<?php class A{}class B extends A{}')));
        self::assertFalse($invocation->instance(new Term('object', 'a', attributes:['class' => 'A']), 'B'));
        self::assertTrue($invocation->instance(new Term('object', 'b', attributes:['class' => 'B']), 'A'));
        self::assertFalse($invocation->instance(null, 'A'));
    }
    /**
     * @throws JsonException If captured source metadata cannot be encoded
     */
    public function testMagicPreservesNamedArgumentKeys(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{function __call($name,$args){return [$name,$args];}}function target(){return (new B)->missing(x:1,y:2);}');
        self::assertSame(['missing',['x' => 1,'y' => 2]], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testErrorRetainsThePostArgumentState(): void
    {
        $state = new State();
        $state->memory->write($state->local('changed'), Term::constant(2));
        $paths = (new Invocation(new Machine(SolverFixture::context())))->error($state);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame(2, $paths[0]->memory->read($paths[0]->local('changed'))->literal);
    }
    /**
     * @param string $source Captured class implementation
     * @param string $name Requested member
     * @param string|null $target Resolved declaration when present
     * @param string $operation Method call syntax
     * @param string $scope Lexical calling scope
     * @param Term $current Existing instance for static syntax
     * @param string $completion Target completion
     * @param string $expectedJson Return value or null when invocation fails
     * @throws JsonException If expected observations cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMemberCalls')]
    public function testCallEnforcesVisibilityInstanceBindingAndMagicDispatch(string $source, string $name, ?string $target, string $operation, string $scope, Term $current, string $completion, string $expectedJson): void
    {
        $context = SolverFixture::context($source);
        $caller = new CallableIR('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1), className:$scope);
        $state = new State();
        $state->memory->write($state->local('this'), $current);
        $receiver = new Term('object', 'box', attributes:['class' => 'Box']);
        $site = new Instruction('call', $operation, $caller->source, 'result');
        $paths = (new Invocation(new Machine($context)))->call($caller, $site, $state, [], $receiver, 'Box', $name, $target);
        self::assertCount(1, $paths);
        self::assertSame($completion, $paths[0]->completion->kind);
        self::assertSame(json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR), ($paths[0]->registers['result'] ?? Term::constant(null))->native());
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return array<string,array{string,string,string|null,string,string,Term,string,string}>
     */
    public static function providerMemberCalls(): array
    {
        $none = Term::constant(null);
        $box = new Term('object', 'box', attributes:['class' => 'Box']);
        return [
            'public instance' => ['<?php class Box{function run(){return 3;}}','run','Box::run','invoke-method','',$none,'normal','3'],
            'private outside' => ['<?php class Box{private function run(){return 3;}}','run','Box::run','invoke-method','',$none,'throw','null'],
            'private inside' => ['<?php class Box{private function run(){return 3;}}','run','Box::run','invoke-method','Box',$none,'normal','3'],
            'protected outside' => ['<?php class Box{protected function run(){return 3;}}','run','Box::run','invoke-method','',$none,'throw','null'],
            'protected child' => ['<?php class Box{protected function run(){return 3;}}class Child extends Box{}','run','Box::run','invoke-method','Child',$none,'normal','3'],
            'missing known method' => ['<?php class Box{}','run',null,'invoke-method','',$none,'throw','null'],
            'magic missing method' => ['<?php class Box{function __call($name,$args){return $name;}}','run',null,'invoke-method','',$none,'normal','"run"'],
            'magic inaccessible method' => ['<?php class Box{private function run(){return 3;}function __call($name,$args){return $name;}}','run','Box::run','invoke-method','',$none,'normal','"run"'],
            'magic missing static' => ['<?php class Box{static function __callStatic($name,$args){return $name;}}','run',null,'invoke-static','',$none,'normal','"run"'],
            'instance requires receiver' => ['<?php class Box{function run(){return 3;}}','run','Box::run','invoke-static','',$none,'throw','null'],
            'instance accepts current receiver' => ['<?php class Box{function run(){return 3;}}','run','Box::run','invoke-static','Box',$box,'normal','3'],
            'static never binds this' => ['<?php class Box{static function run(){return isset($this);}}','run','Box::run','invoke-method','',$box,'normal','false'],
            'abstract rejects invocation' => ['<?php abstract class Box{abstract static function run();}','run','Box::run','invoke-static','',$none,'throw','null'],
        ];
    }

    public function testMagicPreservesBoundCallableLateStaticClass(): void
    {
        $context = SolverFixture::context('<?php class Box{static function __callStatic($name,$args){return [$name,$args,static::class];}}');
        $caller = new CallableIR('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
        $state = new State();
        $state->lateStaticClass = 'Child';
        $site = new Instruction('call', 'invoke-static', $caller->source, 'result', attributes:['bound-callable' => true]);
        $paths = (new Invocation(new Machine($context)))->magic($caller, $site, $state, [new PassedArgument(Term::constant(2), 'named')], Term::constant('Box'), 'Box', 'missing');
        self::assertCount(1, $paths);
        self::assertSame(['missing',['named' => 2],'Child'], $paths[0]->registers['result']->native());
    }

    public function testMagicKeepsUnknownUnpacksAsEffectsAndExceptionalBoundaries(): void
    {
        $context = SolverFixture::context('<?php class Box{function __call($name,$args){return 1;}}');
        $caller = new CallableIR('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
        $paths = (new Invocation(new Machine($context)))->magic($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [new PassedArgument(Term::opaque('unknown'), '*')], new Term('object', 'box', attributes:['class' => 'Box']), 'Box', 'missing');
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column($context->frontiers, 'code'));
    }

    public function testMagicRejectsInvalidArgumentOrderingBeforeItsBody(): void
    {
        $context = SolverFixture::context('<?php class Box{function __call($name,$args){return 1;}}');
        $caller = new CallableIR('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
        $paths = (new Invocation(new Machine($context)))->magic($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [new PassedArgument(Term::constant(1), 'named'),new PassedArgument(Term::constant(2))], new Term('object', 'box', attributes:['class' => 'Box']), 'Box', 'missing');
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $context->frontiers);
    }

    public function testCallUsesNativeSemanticsAndRegisteredModelsBeforeMissingMemberErrors(): void
    {
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example.member', '1', 'Box::read'), new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(Term::constant(5)))]));
        $context = SolverFixture::context('<?php class Box{}', configuration:new \Deriver\Api\Project\Configuration(models:[$model]));
        $caller = new CallableIR('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
        $state = new State();
        $state->memory->write(new \Deriver\Internal\Memory\Location('object:error', ['message']), Term::constant('message'));
        $invocation = new Invocation(new Machine($context));
        $modeled = $invocation->call($caller, new Instruction('model', 'invoke-method', $caller->source, 'result'), $state, [], new Term('object', 'box', attributes:['class' => 'Box']), 'Box', 'read', null);
        $native = $invocation->call($caller, new Instruction('native', 'invoke-method', $caller->source, 'result'), $state, [], new Term('object', 'error', attributes:['class' => 'Exception']), 'Exception', 'getMessage', null);
        self::assertCount(1, $modeled);
        self::assertSame(5, $modeled[0]->registers['result']->native());
        self::assertCount(1, $native);
        self::assertSame('message', $native[0]->registers['result']->native());
        self::assertSame([], $context->frontiers);
    }

    public function testInstanceAcceptsDeclaredReceiverTypesAndRejectsMissingTypeFacts(): void
    {
        $invocation = new Invocation(new Machine(SolverFixture::context('<?php class A{}class B extends A{}')));
        self::assertTrue($invocation->instance(Term::parameter('b', 'B'), 'A'));
        self::assertFalse($invocation->instance(new Term('object', 'x'), 'A'));
        self::assertFalse($invocation->instance(new Term('object', 'x', attributes:['class' => 1]), 'A'));
    }
}
