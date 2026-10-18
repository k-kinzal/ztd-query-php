<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Builtin;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Builtin\FunctionEvaluator;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Builtin\ScalarFunctions;
use Deriver\Deriver\Evaluator\Builtin\TypePredicates;
use Deriver\Deriver\Evaluator\Call\ArgumentBindings;
use Deriver\Deriver\Evaluator\Call\BoundArgument;
use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\PlanActions;
use Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler;
use Deriver\Deriver\Evaluator\Call\Plan\PlanFootprints;
use Deriver\Deriver\Evaluator\Call\Plan\PlanValidation;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\Declaration\Parameter as DeclarationParameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\Inputs;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\NativeArguments;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Modes;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\IntrinsicTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AggregateLowering;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(TypePredicates::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(NativeArguments::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(FunctionEvaluator::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(PlanActions::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(PlanFootprints::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(CallDecision::class)]
#[UsesClass(CallDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeclarationParameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class TypePredicatesTest extends TestCase
{
    public function testApplyPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){return [is_int(1),is_string(1),is_array([]),is_null(null)];}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame([true, false, true, true], $result->normalOutcomes[0]->values['return']->native());
    }
    public function testBoundSeparatesKnownTypeFactsFromMixedUnions(): void
    {
        $predicates = new TypePredicates();
        self::assertTrue($predicates->bound('is_array', new Term(TermKind::External, 'env', attributes: ['type' => 'array'])));
        self::assertFalse($predicates->bound('is_object', Term::parameter('x', 'int|string')));
        self::assertNull($predicates->bound('is_int', Term::parameter('x', 'int|string')));
        self::assertNull($predicates->bound('is_numeric', Term::parameter('x', 'string')));
    }
    /**
     * @param string $name Predicate
     * @param Term $value Runtime category
     * @param bool $expected Known predicate truth
     */
    #[DataProvider('providerConcretePredicates')]
    public function testApplyRecognizesConcreteRuntimeTypes(string $name, Term $value, bool $expected): void
    {
        self::assertSame($expected, (new TypePredicates())->apply($name, $value)->native());
    }
    /**
     * @return iterable<string,array{string,Term,bool}>
     */
    public static function providerConcretePredicates(): iterable
    {
        yield 'string true' => ['is_string', Term::constant('text'), true];
        yield 'string false' => ['is_string', Term::constant(4), false];
        yield 'integer true' => ['is_int', Term::constant(4), true];
        yield 'integer false' => ['is_int', Term::constant(4.0), false];
        yield 'integer alias true' => ['is_integer', Term::constant(4), true];
        yield 'integer alias false' => ['is_integer', Term::constant('4'), false];
        yield 'float true' => ['is_float', Term::constant(4.0), true];
        yield 'float false' => ['is_float', Term::constant(4), false];
        yield 'double alias true' => ['is_double', Term::constant(4.0), true];
        yield 'double alias false' => ['is_double', Term::constant('4.0'), false];
        yield 'boolean true' => ['is_bool', Term::constant(false), true];
        yield 'boolean false' => ['is_bool', Term::constant(0), false];
        yield 'null true' => ['is_null', Term::constant(null), true];
        yield 'null false' => ['is_null', Term::constant(false), false];
        yield 'numeric integer' => ['is_numeric', Term::constant(4), true];
        yield 'numeric string' => ['is_numeric', Term::constant(' 4.5 '), true];
        yield 'numeric false' => ['is_numeric', Term::constant('4suffix'), false];
        yield 'scalar integer' => ['is_scalar', Term::constant(4), true];
        yield 'scalar null' => ['is_scalar', Term::constant(null), false];
        yield 'array false on scalar' => ['is_array', Term::constant(4), false];
        yield 'object false on scalar' => ['is_object', Term::constant(4), false];
        yield 'closed array' => ['is_array', Term::array([]), true];
        yield 'open array' => ['is_array', Term::array([], true), true];
        yield 'array is not object' => ['is_object', Term::array([]), false];
        yield 'object' => ['is_object', new Term(TermKind::Object, 'one'), true];
        yield 'closure object' => ['is_object', new Term(TermKind::Closure, 'body'), true];
        yield 'enum object' => ['is_object', new Term(TermKind::Enum, 'Mode::Ready'), true];
        yield 'object is not array' => ['is_array', new Term(TermKind::Object, 'one'), false];
        yield 'object is not scalar' => ['is_scalar', new Term(TermKind::Object, 'one'), false];
        yield 'array is not scalar' => ['is_scalar', Term::array([]), false];
        yield 'closure callable' => ['is_callable', new Term(TermKind::Closure, 'body'), true];
        yield 'function callable' => ['is_callable', new Term(TermKind::Callable, 'function'), true];
        yield 'method callable' => ['is_callable', new Term(TermKind::CallableMethod, 'method'), true];
    }
    /**
     * @param string $name Predicate
     * @param string $type Finite declared type
     * @param bool|null $expected Proven truth or unresolved result
     */
    #[DataProvider('providerTypeBounds')]
    public function testBoundRequiresEveryUnionArmToSupportTheSameConclusion(string $name, string $type, ?bool $expected): void
    {
        self::assertSame($expected, (new TypePredicates())->bound($name, Term::parameter('input', $type)));
    }
    /**
     * @return iterable<string,array{string,string,bool|null}>
     */
    public static function providerTypeBounds(): iterable
    {
        yield 'int' => ['is_int', 'int', true];
        yield 'integer alias' => ['is_integer', 'int', true];
        yield 'float' => ['is_float', 'float', true];
        yield 'double alias' => ['is_double', 'float', true];
        yield 'string' => ['is_string', 'string', true];
        yield 'bool' => ['is_bool', 'bool', true];
        yield 'true' => ['is_bool', 'true', true];
        yield 'false' => ['is_bool', 'false', true];
        yield 'boolean union' => ['is_bool', 'true|false', true];
        yield 'null' => ['is_null', 'null', true];
        yield 'array' => ['is_array', 'array', true];
        yield 'object' => ['is_object', 'object', true];
        yield 'scalar union' => ['is_scalar', 'int|float|string|bool|true|false', true];
        yield 'numeric union' => ['is_numeric', 'int|float', true];
        yield 'disjoint union' => ['is_int', 'string|float|bool|null|array|object', false];
        yield 'non scalar union' => ['is_scalar', 'array|object|null', false];
        yield 'non numeric union' => ['is_numeric', 'bool|false|null', false];
        yield 'mixed truth union' => ['is_int', 'int|string', null];
        yield 'numeric string unknown' => ['is_numeric', 'string', null];
        yield 'numeric string union unknown' => ['is_numeric', 'int|string', null];
        yield 'mixed type' => ['is_array', 'mixed', null];
        yield 'class type' => ['is_object', 'Box', null];
        yield 'unknown union arm' => ['is_int', 'int|Box', null];
        yield 'unsupported predicate' => ['is_resource', 'int', null];
        yield 'empty type' => ['is_int', '', null];
    }
    public function testBoundRejectsMalformedNonStringTypeMetadata(): void
    {
        self::assertNull((new TypePredicates())->bound('is_int', new Term(TermKind::External, 'input', attributes: ['type' => 7])));
    }
    public function testApplyUsesProvenTypeBounds(): void
    {
        $input = new Term(TermKind::Parameter, 'input', attributes: ['type' => 'int|float']);
        $result = (new TypePredicates())->apply('is_numeric', $input);
        self::assertTrue($result->native());
    }
    /**
     * @param string $name Predicate
     * @param Term $input Input whose truth depends on runtime data or declarations
     */
    #[DataProvider('providerUnresolvedPredicates')]
    public function testApplyRetainsUnknownPredicatesWithTheirInputAndBooleanType(string $name, Term $input): void
    {
        $result = (new TypePredicates())->apply($name, $input);
        self::assertSame(TermKind::Intrinsic, $result->kind);
        self::assertSame($name, $result->literal);
        self::assertSame([$input], $result->operands);
        self::assertSame('bool', $result->attributes['type']);
    }
    /**
     * @return iterable<string,array{string,Term}>
     */
    public static function providerUnresolvedPredicates(): iterable
    {
        yield 'mixed parameter' => ['is_int', Term::parameter('input')];
        yield 'string numeric content' => ['is_numeric', Term::parameter('input', 'string')];
        yield 'class callable protocol' => ['is_callable', new Term(TermKind::Object, 'box', attributes: ['class' => 'Box'])];
        yield 'named function existence' => ['is_callable', Term::constant('function_name')];
        yield 'callback array binding' => ['is_callable', Term::fromNative(['Box', 'run'])];
    }
}
