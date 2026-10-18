<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Builtin;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Builtin\ArrayFunctions;
use Deriver\Deriver\Evaluator\Builtin\FunctionEvaluator;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Builtin\ScalarFunctions;
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
#[CoversClass(Library::class)]
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
#[UsesClass(ArrayFunctions::class)]
#[UsesClass(FunctionEvaluator::class)]
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
final class LibraryTest extends TestCase
{
    public function testEvaluatorPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){return array_merge([2=>"left","x"=>1],[2=>"right","x"=>2]);}';
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
        self::assertSame([0 => 'left', 'x' => 2, 1 => 'right'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testParametersKeepsRequiredRandomBoundsDistinctFromOmittedRandBounds(): void
    {
        $library = new Library();
        $random = $library->parameters('random_int');
        $rand = $library->parameters('rand');
        self::assertNotNull($random);
        self::assertNotNull($rand);
        self::assertNull($random[0]->default);
        self::assertSame(TermKind::Omitted, $rand[0]->default?->kind);
    }
    public function testArraysDeclaresSortWritesAndNamedCollectionArguments(): void
    {
        $library = new Library();
        $sort = $library->arrays('sort');
        self::assertNotNull($sort);
        self::assertTrue($sort[0]->byReference);
        self::assertSame(['array', 'callback', 'initial'], array_column($library->arrays('array_reduce') ?? [], 'name'));
        self::assertNull($library->arrays('unknown'));
    }
    /**
     * @param list<string> $types Accepted target type alternatives
     * @param scalar|null $default Captured target default
     */
    #[DataProvider('providerNativeParameters')]
    public function testParametersMatchCapturedPhp83Signatures(string $function, int $index, int $count, string $name, array $types, bool $reference, bool $variadic, string $defaultKind, int|float|string|bool|null $default): void
    {
        $parameters = (new Library())->parameters($function);
        self::assertNotNull($parameters);
        self::assertCount($count, $parameters);
        $parameter = $parameters[$index];
        self::assertSame($name, $parameter->name);
        self::assertEqualsCanonicalizing($types, explode('|', $parameter->type));
        self::assertSame($reference, $parameter->byReference);
        self::assertSame($variadic, $parameter->variadic);
        self::assertSame($defaultKind, $parameter->default->kind->value ?? 'required');
        self::assertSame($default, $parameter->default?->literal);
    }
    /**
     * @return array<string,array{string,int,int,string,list<string>,bool,bool,string,scalar|null}>
     */
    public static function providerNativeParameters(): array
    {
        return ['strlen:string' => ['strlen', 0, 1, 'string', ['string'], false, false, 'required', null], 'strtolower:string' => ['strtolower', 0, 1, 'string', ['string'], false, false, 'required', null], 'strtoupper:string' => ['strtoupper', 0, 1, 'string', ['string'], false, false, 'required', null], 'trim:string' => ['trim', 0, 2, 'string', ['string'], false, false, 'required', null], 'trim:characters' => ['trim', 1, 2, 'characters', ['string'], false, false, 'constant', " \n\r\t\v\x00"], 'implode:separator' => ['implode', 0, 2, 'separator', ['array', 'string'], false, false, 'required', null], 'implode:array' => ['implode', 1, 2, 'array', ['array', 'null'], false, false, 'constant', null], 'join:separator' => ['join', 0, 2, 'separator', ['array', 'string'], false, false, 'required', null], 'join:array' => ['join', 1, 2, 'array', ['array', 'null'], false, false, 'constant', null], 'explode:separator' => ['explode', 0, 3, 'separator', ['string'], false, false, 'required', null], 'explode:string' => ['explode', 1, 3, 'string', ['string'], false, false, 'required', null], 'explode:limit' => ['explode', 2, 3, 'limit', ['int'], false, false, 'constant', 9223372036854775807], 'substr:string' => ['substr', 0, 3, 'string', ['string'], false, false, 'required', null], 'substr:offset' => ['substr', 1, 3, 'offset', ['int'], false, false, 'required', null], 'substr:length' => ['substr', 2, 3, 'length', ['int', 'null'], false, false, 'constant', null], 'sprintf:format' => ['sprintf', 0, 2, 'format', ['string'], false, false, 'required', null], 'sprintf:values' => ['sprintf', 1, 2, 'values', ['mixed'], false, true, 'required', null], 'str_replace:search' => ['str_replace', 0, 4, 'search', ['array', 'string'], false, false, 'required', null], 'str_replace:replace' => ['str_replace', 1, 4, 'replace', ['array', 'string'], false, false, 'required', null], 'str_replace:subject' => ['str_replace', 2, 4, 'subject', ['array', 'string'], false, false, 'required', null], 'str_replace:count' => ['str_replace', 3, 4, 'count', ['mixed'], true, false, 'constant', null], 'getenv:name' => ['getenv', 0, 2, 'name', ['string', 'null'], false, false, 'constant', null], 'getenv:local_only' => ['getenv', 1, 2, 'local_only', ['bool'], false, false, 'constant', false], 'random_int:min' => ['random_int', 0, 2, 'min', ['int'], false, false, 'required', null], 'random_int:max' => ['random_int', 1, 2, 'max', ['int'], false, false, 'required', null], 'mt_rand:min' => ['mt_rand', 0, 2, 'min', ['int'], false, false, 'omitted', null], 'mt_rand:max' => ['mt_rand', 1, 2, 'max', ['int'], false, false, 'omitted', null], 'rand:min' => ['rand', 0, 2, 'min', ['int'], false, false, 'omitted', null], 'rand:max' => ['rand', 1, 2, 'max', ['int'], false, false, 'omitted', null], 'get_class:object' => ['get_class', 0, 1, 'object', ['object'], false, false, 'omitted', null], 'microtime:as_float' => ['microtime', 0, 1, 'as_float', ['bool'], false, false, 'constant', false], 'is_array:value' => ['is_array', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_string:value' => ['is_string', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_int:value' => ['is_int', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_integer:value' => ['is_integer', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_float:value' => ['is_float', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_double:value' => ['is_double', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_bool:value' => ['is_bool', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_null:value' => ['is_null', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_object:value' => ['is_object', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_numeric:value' => ['is_numeric', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_scalar:value' => ['is_scalar', 0, 1, 'value', ['mixed'], false, false, 'required', null], 'is_callable:value' => ['is_callable', 0, 3, 'value', ['mixed'], false, false, 'required', null], 'is_callable:syntax_only' => ['is_callable', 1, 3, 'syntax_only', ['bool'], false, false, 'constant', false], 'is_callable:callable_name' => ['is_callable', 2, 3, 'callable_name', ['mixed'], true, false, 'omitted', null], 'count:value' => ['count', 0, 2, 'value', ['Countable', 'array'], false, false, 'required', null], 'count:mode' => ['count', 1, 2, 'mode', ['int'], false, false, 'constant', 0], 'array_values:array' => ['array_values', 0, 1, 'array', ['array'], false, false, 'required', null], 'array_keys:array' => ['array_keys', 0, 3, 'array', ['array'], false, false, 'required', null], 'array_keys:filter_value' => ['array_keys', 1, 3, 'filter_value', ['mixed'], false, false, 'omitted', null], 'array_keys:strict' => ['array_keys', 2, 3, 'strict', ['bool'], false, false, 'constant', false], 'array_merge:arrays' => ['array_merge', 0, 1, 'arrays', ['array'], false, true, 'required', null], 'in_array:needle' => ['in_array', 0, 3, 'needle', ['mixed'], false, false, 'required', null], 'in_array:haystack' => ['in_array', 1, 3, 'haystack', ['array'], false, false, 'required', null], 'in_array:strict' => ['in_array', 2, 3, 'strict', ['bool'], false, false, 'constant', false], 'array_key_exists:key' => ['array_key_exists', 0, 2, 'key', ['mixed'], false, false, 'required', null], 'array_key_exists:array' => ['array_key_exists', 1, 2, 'array', ['array'], false, false, 'required', null], 'array_map:callback' => ['array_map', 0, 3, 'callback', ['callable', 'null'], false, false, 'required', null], 'array_map:array' => ['array_map', 1, 3, 'array', ['array'], false, false, 'required', null], 'array_map:arrays' => ['array_map', 2, 3, 'arrays', ['array'], false, true, 'required', null], 'array_filter:array' => ['array_filter', 0, 3, 'array', ['array'], false, false, 'required', null], 'array_filter:callback' => ['array_filter', 1, 3, 'callback', ['callable', 'null'], false, false, 'constant', null], 'array_filter:mode' => ['array_filter', 2, 3, 'mode', ['int'], false, false, 'constant', 0], 'array_reduce:array' => ['array_reduce', 0, 3, 'array', ['array'], false, false, 'required', null], 'array_reduce:callback' => ['array_reduce', 1, 3, 'callback', ['callable'], false, false, 'required', null], 'array_reduce:initial' => ['array_reduce', 2, 3, 'initial', ['mixed'], false, false, 'constant', null], 'sort:array' => ['sort', 0, 2, 'array', ['array'], true, false, 'required', null], 'sort:flags' => ['sort', 1, 2, 'flags', ['int'], false, false, 'constant', 0], 'array_shift:array' => ['array_shift', 0, 1, 'array', ['array'], true, false, 'required', null], 'array_pop:array' => ['array_pop', 0, 1, 'array', ['array'], true, false, 'required', null], 'array_push:array' => ['array_push', 0, 2, 'array', ['array'], true, false, 'required', null], 'array_push:values' => ['array_push', 1, 2, 'values', ['mixed'], false, true, 'required', null], 'array_unshift:array' => ['array_unshift', 0, 2, 'array', ['array'], true, false, 'required', null], 'array_unshift:values' => ['array_unshift', 1, 2, 'values', ['mixed'], false, true, 'required', null], 'array_key_first:array' => ['array_key_first', 0, 1, 'array', ['array'], false, false, 'required', null], 'array_key_last:array' => ['array_key_last', 0, 1, 'array', ['array'], false, false, 'required', null], 'array_slice:array' => ['array_slice', 0, 4, 'array', ['array'], false, false, 'required', null], 'array_slice:offset' => ['array_slice', 1, 4, 'offset', ['int'], false, false, 'required', null], 'array_slice:length' => ['array_slice', 2, 4, 'length', ['int', 'null'], false, false, 'constant', null], 'array_slice:preserve_keys' => ['array_slice', 3, 4, 'preserve_keys', ['bool'], false, false, 'constant', false]];
    }
    public function testEvaluatorNormalizesQualifiedCaseInsensitiveBuiltinsAndRejectsUnknownNames(): void
    {
        $library = new Library();
        $evaluator = $library->evaluator('\STRLEN');
        self::assertNotNull($evaluator);
        self::assertSame('php.strlen', $evaluator->descriptor()->id);
        self::assertNull($library->evaluator('unregistered_function'));
        self::assertNull($library->parameters('unregistered_function'));
        self::assertSame([], $library->parameters('time'));
    }
    public function testValueFunctionsUsePhpParameterNames(): void
    {
        $parameters = (new Library())->valueFunctions('str_repeat');
        self::assertNotNull($parameters);
        self::assertSame(['string', 'times'], array_column($parameters, 'name'));
    }
}
