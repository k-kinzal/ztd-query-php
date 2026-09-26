<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
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
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $state = new \Deriver\Internal\Solver\State();
        $location = $state->memory->allocate(\Deriver\Value\Term::constant(1));
        $actual = new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant('1.5'), location: $location);
        $binding = new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        self::assertSame(1, $binding->value(new \Deriver\Internal\IR\Parameter('x', byReference: true), $actual, $state)->native());
        self::assertSame('1.5', $binding->value(new \Deriver\Internal\IR\Parameter('x'), $actual, $state)->native());
    }
    /**
     * @param \Deriver\Internal\IR\Parameter $parameter Symbolic signature
     * @param string $expectedType Resolved valid-input type
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSymbolicParameters')]
    public function testBindKeepsSymbolicInputsIndependentOfOmission(\Deriver\Internal\IR\Parameter $parameter, string $expectedType): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box{function target(self $value){}}');
        $callable = $context->program->callable('Box::target');
        self::assertNotNull($callable);
        $state = new \Deriver\Internal\Solver\State();
        $paths = (new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context)))->bind($callable, $parameter, null, $state, true);
        self::assertSame([$state], $paths);
        self::assertSame('normal', $state->completion->kind);
        $value = $state->memory->read($state->local($parameter->name));
        self::assertSame('parameter', $value->kind);
        self::assertSame($parameter->name, $value->literal);
        self::assertSame($expectedType, $value->attributes['type']);
    }

    /**
     * @return array<string, array{\Deriver\Internal\IR\Parameter,string}>
     */
    public static function providerSymbolicParameters(): array
    {
        return [
            'scalar' => [new \Deriver\Internal\IR\Parameter('value', 'int'),'int'],
            'reference' => [new \Deriver\Internal\IR\Parameter('value', 'int', byReference:true),'int'],
            'variadic collection' => [new \Deriver\Internal\IR\Parameter('items', 'int', variadic:true),'array'],
            'lexical class' => [new \Deriver\Internal\IR\Parameter('value', 'self'),'Box'],
        ];
    }

    /**
     * @param \Deriver\Internal\IR\Parameter $parameter Selected signature
     * @param \Deriver\Internal\Solver\Call\PassedArgument|null $actual Omitted or invalid actual
     * @param string $exception Expected target error
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidArguments')]
    public function testBindRejectsInvalidArgumentsWithoutInitializingTheLocal(\Deriver\Internal\IR\Parameter $parameter, ?\Deriver\Internal\Solver\Call\PassedArgument $actual, string $exception): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new \Deriver\Internal\Solver\State();
        $paths = (new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context)))->bind($callable, $parameter, $actual, $state, false, true);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $state->completion->kind);
        self::assertSame($exception, $state->completion->value?->literal);
        self::assertSame([], $state->locals);
    }

    /**
     * @return array<string, array{\Deriver\Internal\IR\Parameter,\Deriver\Internal\Solver\Call\PassedArgument|null,string}>
     */
    public static function providerInvalidArguments(): array
    {
        return [
            'missing required' => [new \Deriver\Internal\IR\Parameter('x'),null,'ArgumentCountError'],
            'invalid scalar type' => [new \Deriver\Internal\IR\Parameter('x', 'int'),new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant('1')),'TypeError'],
            'reference requires address' => [new \Deriver\Internal\IR\Parameter('x', byReference:true),new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1)),'Error'],
            'reference requires writable address' => [new \Deriver\Internal\IR\Parameter('x', byReference:true),new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1), location:new \Deriver\Internal\Memory\Location('readonly'), writable:false),'Error'],
        ];
    }

    public function testBindPreservesBothOutcomesWhenAnActualMayViolateItsType(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new \Deriver\Internal\Solver\State();
        $actual = new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::parameter('input'));
        $paths = (new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context)))->bind($callable, new \Deriver\Internal\IR\Parameter('x', 'int'), $actual, $state, false, true);
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
        $state = new \Deriver\Internal\Solver\State();
        $location = $state->memory->allocate(\Deriver\Value\Term::constant('2'));
        $actual = new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant('2'), location:$location);
        $paths = (new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context)))->bind($callable, new \Deriver\Internal\IR\Parameter('x', 'int', byReference:true), $actual, $state, false);
        self::assertSame([$state], $paths);
        self::assertSame('normal', $state->completion->kind);
        self::assertSame(2, $state->memory->read($location)->native());
        $state->memory->write($state->local('x'), \Deriver\Value\Term::constant(3));
        self::assertSame(3, $state->memory->read($location)->native());
    }

    public function testVariadicRetainsNamedAliasesAndAppliesCoercionsToEveryCallerCell(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $first = $state->memory->allocate(\Deriver\Value\Term::constant('2'));
        $second = $state->memory->allocate(\Deriver\Value\Term::constant('3'));
        $actual = new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::array([]), elements:[
            new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant('2'), location:$first),
            'named' => new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant('3'), location:$second),
        ]);
        $binding = new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        self::assertSame([$state], $binding->variadic(new \Deriver\Internal\IR\Parameter('items', 'int', byReference:true, variadic:true), $actual, $state, false));
        self::assertSame(2, $state->memory->read($first)->native());
        self::assertSame(3, $state->memory->read($second)->native());
        $items = $state->memory->read($state->local('items'));
        self::assertSame([0,'named'], array_keys($items->operands));
        self::assertSame('cell', $items->operands['named']->kind);
        $state->memory->write(new \Deriver\Internal\Memory\Location($state->local('items')->root, ['named']), \Deriver\Value\Term::constant(4));
        self::assertSame(4, $state->memory->read($second)->native());
    }

    /**
     * @param bool $byReference Whether an address is required
     * @param \Deriver\Internal\Solver\Call\PassedArgument $element Invalid variadic element
     * @param string $exception Target error class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidVariadics')]
    public function testVariadicRejectsAnInvalidElementBeforePublishingTheCollection(bool $byReference, \Deriver\Internal\Solver\Call\PassedArgument $element, string $exception): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $binding = new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        $paths = $binding->variadic(new \Deriver\Internal\IR\Parameter('items', 'int', byReference:$byReference, variadic:true), new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::array([]), elements:[$element]), $state, true);
        self::assertSame([$state], $paths);
        self::assertSame('throw', $state->completion->kind);
        self::assertSame($exception, $state->completion->value?->literal);
        self::assertSame([], $state->locals);
    }

    /**
     * @return array<string,array{bool,\Deriver\Internal\Solver\Call\PassedArgument,string}>
     */
    public static function providerInvalidVariadics(): array
    {
        return [
            'type error' => [false,new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant('2')),'TypeError'],
            'missing address' => [true,new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(2)),'Error'],
            'readonly address' => [true,new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(2), location:new \Deriver\Internal\Memory\Location('readonly'), writable:false),'Error'],
        ];
    }

    public function testVariadicPreservesPossibleTypeFailureAlongsideTheNormalCollection(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $binding = new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        $paths = $binding->variadic(new \Deriver\Internal\IR\Parameter('items', 'self', variadic:true), new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::array([]), elements:[
            new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::parameter('unknown')),
            new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1)),
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
        $default = new \Deriver\Internal\IR\CallableIR('default', [], [
            new \Deriver\Internal\IR\BasicBlock(0, [new \Deriver\Internal\IR\Instruction('default', 'constant', $callable->source, 'r', constant:new \Deriver\Value\Term('omitted'))], new \Deriver\Internal\IR\Terminator('return', 'r')),
        ], $callable->source);
        $state = new \Deriver\Internal\Solver\State();
        $paths = (new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context)))->defaultArgument($callable, new \Deriver\Internal\IR\Parameter('optional', 'int'), $default, $state, true);
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
        $state = new \Deriver\Internal\Solver\State();
        $paths = (new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context)))->defaultArgument($callable, $callable->parameters[0], $default, $state, true);
        self::assertCount(2, $paths);
        self::assertEqualsCanonicalizing(['normal','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame(7, $paths[0]->memory->read(new \Deriver\Internal\Memory\Location('global:count'))->native());
        self::assertSame(7, $paths[1]->memory->read(new \Deriver\Internal\Memory\Location('global:count'))->native());
        self::assertSame('uninitialized', $state->memory->read(new \Deriver\Internal\Memory\Location('global:count'))->kind);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }

    public function testVariadicReportsWeakScalarWarningsAtTheBindingSource(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new \Deriver\Internal\Solver\State();
        $binding = new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context));
        $paths = $binding->variadic(new \Deriver\Internal\IR\Parameter('items', 'int', variadic:true), new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::array([]), elements:[new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1.5))]), $state, false, source:$callable->source);
        self::assertSame([$state], $paths);
        self::assertSame([1], $state->memory->read($state->local('items'))->native());
        self::assertSame(['PHP_WARNING'], array_column($context->frontiers, 'code'));
    }

    public function testVariadicRetainsArbitraryThrowableFromAnUnresolvedObjectCoercion(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Text{function __toString(){return "text";}}function target(){}');
        $callable = $context->program->callable('target');
        self::assertNotNull($callable);
        $state = new \Deriver\Internal\Solver\State();
        $binding = new \Deriver\Internal\Solver\Call\ParameterBinding(new \Deriver\Internal\Solver\Machine($context));
        $actual = new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::array([]), elements:[
            new \Deriver\Internal\Solver\Call\PassedArgument(new \Deriver\Value\Term('object', 'text', attributes:['class' => 'Text'])),
            new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant('last')),
        ]);
        $paths = $binding->variadic(new \Deriver\Internal\IR\Parameter('items', 'string', variadic:true), $actual, $state, false, source:$callable->source);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('Throwable', $paths[1]->completion->value?->literal);
        self::assertTrue($paths[1]->completion->value->attributes['uncertain']);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column($context->frontiers, 'code'));
    }
}
