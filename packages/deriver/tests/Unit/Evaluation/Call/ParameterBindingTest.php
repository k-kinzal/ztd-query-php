<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParameterBinding::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
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
#[UsesClass(\Deriver\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
