<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Member;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Model\Inputs;
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
use Deriver\Evaluation\Call\ProviderDispatch;
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
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Builtin\Library;
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
use Deriver\Model\Provider\DispatchRequest;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ModelPrecedence;
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
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\ConditionalLowering;
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
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Evaluation\Call\Member\Invocation
 */
#[CoversClass(Invocation::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
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
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(Inputs::class)]
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
#[UsesClass(ProviderDispatch::class)]
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
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(Library::class)]
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
#[UsesClass(DispatchRequest::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ModelPrecedence::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ConditionalLowering::class)]
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
