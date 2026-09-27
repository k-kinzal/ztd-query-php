<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Member;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Project\Configuration;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Evaluation\Call\Member\Invocation
 */
#[CoversClass(Invocation::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
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
#[UsesClass(Target::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ModelPrecedence::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
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
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ConditionalLowering::class)]
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
        $caller = new CallableGraph('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1), className:$scope);
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
        $caller = new CallableGraph('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
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
        $caller = new CallableGraph('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
        $paths = (new Invocation(new Machine($context)))->magic($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [new PassedArgument(Term::opaque('unknown'), '*')], new Term('object', 'box', attributes:['class' => 'Box']), 'Box', 'missing');
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column($context->frontiers, 'code'));
    }

    public function testMagicRejectsInvalidArgumentOrderingBeforeItsBody(): void
    {
        $context = SolverFixture::context('<?php class Box{function __call($name,$args){return 1;}}');
        $caller = new CallableGraph('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
        $paths = (new Invocation(new Machine($context)))->magic($caller, new Instruction('call', 'invoke-method', $caller->source, 'result'), new State(), [new PassedArgument(Term::constant(1), 'named'),new PassedArgument(Term::constant(2))], new Term('object', 'box', attributes:['class' => 'Box']), 'Box', 'missing');
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame([], $context->frontiers);
    }

    public function testCallUsesNativeSemanticsAndRegisteredModelsBeforeMissingMemberErrors(): void
    {
        $model = new \Tests\Fake\PlanModel(new ModelDescriptor('example.member', '1', 'Box::read'), new SemanticPlan([Action::returns(Expression::literal(Term::constant(5)))]));
        $context = SolverFixture::context('<?php class Box{}', configuration:new Configuration(models:[$model]));
        $caller = new CallableGraph('caller', [], [], new SourceRef('test', 'fixture.php', 0, 1));
        $state = new State();
        $state->memory->write(new Location('object:error', ['message']), Term::constant('message'));
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
