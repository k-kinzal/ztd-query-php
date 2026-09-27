<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

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
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
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
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyReference;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
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
use Deriver\Value\IntegerConversion;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParameterBinding::class)]
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
#[UsesClass(CallableCheck::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
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
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyReference::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(IntegerConversion::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ParameterBindingTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testBindPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{function __construct(public string $value){}}function target(){return (new Box("yes"))->value;}');
        self::assertSame('yes', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testVariadicPreservesNamedKeysAndChecksEveryElement(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f(int ...$items){return $items;}function target(){return f(1,second:2);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([0 => 1,'second' => 2], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDefaultArgumentRunsOnlyWhenTheCallerOmitsIt(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function f($x=3){return $x;}function target(){return [f(),f(9)];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([3,9], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testVariadicPreservesTypedPropertyConstraintsBeforeCreatingAReference(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $x=1;}function change(float &...$values){$values[0]=2.5;}function target(){$b=new B;try{change($b->x);}catch(TypeError $e){return $b->x;}return 999;}');
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testValueReadsTheCurrentReferenceCellAfterEarlierParameterCoercions(): void
    {
        $state = new State();
        $location = $state->memory->allocate(Term::constant(1));
        $actual = new PassedArgument(Term::constant('1.5'), location: $location);
        $binding = new ParameterBinding(new Machine(\Tests\Fake\SolverFixture::context()));
        self::assertSame(1, $binding->value(new Parameter('x', byReference: true), $actual, $state)->native());
        self::assertSame('1.5', $binding->value(new Parameter('x'), $actual, $state)->native());
    }
    /**
     * @param Parameter $parameter Symbolic signature
     * @param string $expectedType Resolved valid-input type
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSymbolicParameters')]
    public function testBindKeepsSymbolicInputsIndependentOfOmission(Parameter $parameter, string $expectedType): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box{function target(self $value){}}');
        $callable = $context->program->callable('Box::target');
        self::assertNotNull($callable);
        $state = new State();
        $paths = (new ParameterBinding(new Machine($context)))->bind($callable, $parameter, null, $state, true);
        self::assertSame([$state], $paths);
        self::assertSame('normal', $state->completion->kind);
        $value = $state->memory->read($state->local($parameter->name));
        self::assertSame('parameter', $value->kind);
        self::assertSame($parameter->name, $value->literal);
        self::assertSame($expectedType, $value->attributes['type']);
    }

    /**
     * @return array<string, array{Parameter,string}>
     */
    public static function providerSymbolicParameters(): array
    {
        return [
            'scalar' => [new Parameter('value', 'int'),'int'],
            'reference' => [new Parameter('value', 'int', byReference:true),'int'],
            'variadic collection' => [new Parameter('items', 'int', variadic:true),'array'],
            'lexical class' => [new Parameter('value', 'self'),'Box'],
        ];
    }

    /**
     * @param Parameter $parameter Selected signature
     * @param PassedArgument|null $actual Omitted or invalid actual
     * @param string $exception Expected target error
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidArguments')]
    public function testBindRejectsInvalidArgumentsWithoutInitializingTheLocal(Parameter $parameter, ?PassedArgument $actual, string $exception): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new State();
        $paths = (new ParameterBinding(new Machine($context)))->bind($callable, $parameter, $actual, $state, false, true);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $state->completion->kind);
        self::assertSame($exception, $state->completion->value?->literal);
        self::assertSame([], $state->locals);
    }

    /**
     * @return array<string, array{Parameter,PassedArgument|null,string}>
     */
    public static function providerInvalidArguments(): array
    {
        return [
            'missing required' => [new Parameter('x'),null,'ArgumentCountError'],
            'invalid scalar type' => [new Parameter('x', 'int'),new PassedArgument(Term::constant('1')),'TypeError'],
            'reference requires address' => [new Parameter('x', byReference:true),new PassedArgument(Term::constant(1)),'Error'],
            'reference requires writable address' => [new Parameter('x', byReference:true),new PassedArgument(Term::constant(1), location:new Location('readonly'), writable:false),'Error'],
        ];
    }

    public function testBindPreservesBothOutcomesWhenAnActualMayViolateItsType(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new State();
        $actual = new PassedArgument(Term::parameter('input'));
        $paths = (new ParameterBinding(new Machine($context)))->bind($callable, new Parameter('x', 'int'), $actual, $state, false, true);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('int', $paths[0]->memory->read($paths[0]->local('x'))->attributes['type']);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('TypeError', $paths[1]->completion->value?->literal);
        self::assertSame([], $paths[1]->locals);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }

    public function testBindUpdatesACallerReferenceAfterWeakCoercion(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new State();
        $location = $state->memory->allocate(Term::constant('2'));
        $actual = new PassedArgument(Term::constant('2'), location:$location);
        $paths = (new ParameterBinding(new Machine($context)))->bind($callable, new Parameter('x', 'int', byReference:true), $actual, $state, false);
        self::assertSame([$state], $paths);
        self::assertSame('normal', $state->completion->kind);
        self::assertSame(2, $state->memory->read($location)->native());
        $state->memory->write($state->local('x'), Term::constant(3));
        self::assertSame(3, $state->memory->read($location)->native());
    }

    public function testVariadicRetainsNamedAliasesAndAppliesCoercionsToEveryCallerCell(): void
    {
        $state = new State();
        $first = $state->memory->allocate(Term::constant('2'));
        $second = $state->memory->allocate(Term::constant('3'));
        $actual = new PassedArgument(Term::array([]), elements:[
            new PassedArgument(Term::constant('2'), location:$first),
            'named' => new PassedArgument(Term::constant('3'), location:$second),
        ]);
        $binding = new ParameterBinding(new Machine(\Tests\Fake\SolverFixture::context()));
        self::assertSame([$state], $binding->variadic(new Parameter('items', 'int', byReference:true, variadic:true), $actual, $state, false));
        self::assertSame(2, $state->memory->read($first)->native());
        self::assertSame(3, $state->memory->read($second)->native());
        $items = $state->memory->read($state->local('items'));
        self::assertSame([0,'named'], array_keys($items->operands));
        self::assertSame('cell', $items->operands['named']->kind);
        $state->memory->write(new Location($state->local('items')->root, ['named']), Term::constant(4));
        self::assertSame(4, $state->memory->read($second)->native());
    }

    /**
     * @param bool $byReference Whether an address is required
     * @param PassedArgument $element Invalid variadic element
     * @param string $exception Target error class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidVariadics')]
    public function testVariadicRejectsAnInvalidElementBeforePublishingTheCollection(bool $byReference, PassedArgument $element, string $exception): void
    {
        $state = new State();
        $binding = new ParameterBinding(new Machine(\Tests\Fake\SolverFixture::context()));
        $paths = $binding->variadic(new Parameter('items', 'int', byReference:$byReference, variadic:true), new PassedArgument(Term::array([]), elements:[$element]), $state, true);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $state->completion->kind);
        self::assertSame($exception, $state->completion->value?->literal);
        self::assertSame([], $state->locals);
    }

    /**
     * @return array<string,array{bool,PassedArgument,string}>
     */
    public static function providerInvalidVariadics(): array
    {
        return [
            'type error' => [false,new PassedArgument(Term::constant('2')),'TypeError'],
            'missing address' => [true,new PassedArgument(Term::constant(2)),'Error'],
            'readonly address' => [true,new PassedArgument(Term::constant(2), location:new Location('readonly'), writable:false),'Error'],
        ];
    }

    public function testVariadicPreservesPossibleTypeFailureAlongsideTheNormalCollection(): void
    {
        $state = new State();
        $binding = new ParameterBinding(new Machine(\Tests\Fake\SolverFixture::context()));
        $paths = $binding->variadic(new Parameter('items', 'self', variadic:true), new PassedArgument(Term::array([]), elements:[
            new PassedArgument(Term::parameter('unknown')),
            new PassedArgument(Term::constant(1)),
        ]), $state, true, 'int');
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        $items = $paths[0]->memory->read($paths[0]->local('items'));
        self::assertSame('int', $items->operands[0]->attributes['type']);
        self::assertSame(1, $items->operands[1]->native());
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('TypeError', $paths[1]->completion->value?->literal);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }
    public function testDefaultArgumentRetainsAnOmittedMarkerWithoutEnforcingAnAbsentType(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $default = new CallableGraph('default', [], [
            new BasicBlock(0, [new Instruction('default', 'constant', $callable->source, 'r', constant:new Term('omitted'))], new Terminator('return', 'r')),
        ], $callable->source);
        $state = new State();
        $paths = (new ParameterBinding(new Machine($context)))->defaultArgument($callable, new Parameter('optional', 'int'), $default, $state, true);
        self::assertCount(1, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('omitted', $paths[0]->memory->read($paths[0]->local('optional'))->kind);
        self::assertSame([], $state->locals);
        self::assertNotSame($state->memory, $paths[0]->memory);
    }

    public function testDefaultArgumentPreservesInitializerEffectsAndEveryCompletion(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Initializer{function __construct(){global $flag,$count;$count=7;if($flag){throw new Exception;}}}function target($x=new Initializer){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $default = $callable->parameters[0]->default;
        self::assertNotNull($default);
        $state = new State();
        $paths = (new ParameterBinding(new Machine($context)))->defaultArgument($callable, $callable->parameters[0], $default, $state, true);
        self::assertCount(2, $paths);
        self::assertEqualsCanonicalizing(['normal','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame(7, $paths[0]->memory->read(new Location('global:count'))->native());
        self::assertSame(7, $paths[1]->memory->read(new Location('global:count'))->native());
        self::assertSame('uninitialized', $state->memory->read(new Location('global:count'))->kind);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }

    public function testVariadicReportsWeakScalarWarningsAtTheBindingSource(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new State();
        $binding = new ParameterBinding(new Machine($context));
        $paths = $binding->variadic(new Parameter('items', 'int', variadic:true), new PassedArgument(Term::array([]), elements:[new PassedArgument(Term::constant(1.5))]), $state, false, source:$callable->source);
        self::assertSame([$state], $paths);
        self::assertSame([1], $state->memory->read($state->local('items'))->native());
        self::assertSame(['PHP_WARNING'], array_column($context->frontiers, 'code'));
    }

    public function testVariadicRetainsArbitraryThrowableFromAnUnresolvedObjectCoercion(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Text{function __toString(){return "text";}}function target(){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new State();
        $binding = new ParameterBinding(new Machine($context));
        $actual = new PassedArgument(Term::array([]), elements:[
            new PassedArgument(new Term('object', 'text', attributes:['class' => 'Text'])),
            new PassedArgument(Term::constant('last')),
        ]);
        $paths = $binding->variadic(new Parameter('items', 'string', variadic:true), $actual, $state, false, source:$callable->source);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('Throwable', $paths[1]->completion->value?->literal);
        self::assertTrue($paths[1]->completion->value->attributes['uncertain']);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column($context->frontiers, 'code'));
    }
}
