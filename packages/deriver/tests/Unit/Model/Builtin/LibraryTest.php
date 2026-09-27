<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
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
use Deriver\Memory\StorageCapture;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Builtin\ArrayFunctions;
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
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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

#[CoversClass(Library::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
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
#[UsesClass(FunctionModel::class)]
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
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
final class LibraryTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testModelPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_merge([2=>"left","x"=>1],[2=>"right","x"=>2]);}');
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
        self::assertSame('omitted', $rand[0]->default?->kind);
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
        self::assertSame($defaultKind, $parameter->default->kind ?? 'required');
        self::assertSame($default, $parameter->default?->literal);
    }

    /**
     * @return array<string,array{string,int,int,string,list<string>,bool,bool,string,scalar|null}>
     */
    public static function providerNativeParameters(): array
    {
        return [
            'strlen:string' => ['strlen', 0, 1, 'string', ['string'], false, false, 'required', null],
            'strtolower:string' => ['strtolower', 0, 1, 'string', ['string'], false, false, 'required', null],
            'strtoupper:string' => ['strtoupper', 0, 1, 'string', ['string'], false, false, 'required', null],
            'trim:string' => ['trim', 0, 2, 'string', ['string'], false, false, 'required', null],
            'trim:characters' => ['trim', 1, 2, 'characters', ['string'], false, false, 'constant', " \x0a\x0d\x09\x0b\x00"],
            'implode:separator' => ['implode', 0, 2, 'separator', ['array', 'string'], false, false, 'required', null],
            'implode:array' => ['implode', 1, 2, 'array', ['array', 'null'], false, false, 'constant', null],
            'join:separator' => ['join', 0, 2, 'separator', ['array', 'string'], false, false, 'required', null],
            'join:array' => ['join', 1, 2, 'array', ['array', 'null'], false, false, 'constant', null],
            'explode:separator' => ['explode', 0, 3, 'separator', ['string'], false, false, 'required', null],
            'explode:string' => ['explode', 1, 3, 'string', ['string'], false, false, 'required', null],
            'explode:limit' => ['explode', 2, 3, 'limit', ['int'], false, false, 'constant', 9223372036854775807],
            'substr:string' => ['substr', 0, 3, 'string', ['string'], false, false, 'required', null],
            'substr:offset' => ['substr', 1, 3, 'offset', ['int'], false, false, 'required', null],
            'substr:length' => ['substr', 2, 3, 'length', ['int', 'null'], false, false, 'constant', null],
            'sprintf:format' => ['sprintf', 0, 2, 'format', ['string'], false, false, 'required', null],
            'sprintf:values' => ['sprintf', 1, 2, 'values', ['mixed'], false, true, 'required', null],
            'str_replace:search' => ['str_replace', 0, 4, 'search', ['array', 'string'], false, false, 'required', null],
            'str_replace:replace' => ['str_replace', 1, 4, 'replace', ['array', 'string'], false, false, 'required', null],
            'str_replace:subject' => ['str_replace', 2, 4, 'subject', ['array', 'string'], false, false, 'required', null],
            'str_replace:count' => ['str_replace', 3, 4, 'count', ['mixed'], true, false, 'constant', null],
            'getenv:name' => ['getenv', 0, 2, 'name', ['string', 'null'], false, false, 'constant', null],
            'getenv:local_only' => ['getenv', 1, 2, 'local_only', ['bool'], false, false, 'constant', false],
            'random_int:min' => ['random_int', 0, 2, 'min', ['int'], false, false, 'required', null],
            'random_int:max' => ['random_int', 1, 2, 'max', ['int'], false, false, 'required', null],
            'mt_rand:min' => ['mt_rand', 0, 2, 'min', ['int'], false, false, 'omitted', null],
            'mt_rand:max' => ['mt_rand', 1, 2, 'max', ['int'], false, false, 'omitted', null],
            'rand:min' => ['rand', 0, 2, 'min', ['int'], false, false, 'omitted', null],
            'rand:max' => ['rand', 1, 2, 'max', ['int'], false, false, 'omitted', null],
            'get_class:object' => ['get_class', 0, 1, 'object', ['object'], false, false, 'omitted', null],
            'microtime:as_float' => ['microtime', 0, 1, 'as_float', ['bool'], false, false, 'constant', false],
            'is_array:value' => ['is_array', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_string:value' => ['is_string', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_int:value' => ['is_int', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_integer:value' => ['is_integer', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_float:value' => ['is_float', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_double:value' => ['is_double', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_bool:value' => ['is_bool', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_null:value' => ['is_null', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_object:value' => ['is_object', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_numeric:value' => ['is_numeric', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_scalar:value' => ['is_scalar', 0, 1, 'value', ['mixed'], false, false, 'required', null],
            'is_callable:value' => ['is_callable', 0, 3, 'value', ['mixed'], false, false, 'required', null],
            'is_callable:syntax_only' => ['is_callable', 1, 3, 'syntax_only', ['bool'], false, false, 'constant', false],
            'is_callable:callable_name' => ['is_callable', 2, 3, 'callable_name', ['mixed'], true, false, 'omitted', null],
            'count:value' => ['count', 0, 2, 'value', ['Countable', 'array'], false, false, 'required', null],
            'count:mode' => ['count', 1, 2, 'mode', ['int'], false, false, 'constant', 0],
            'array_values:array' => ['array_values', 0, 1, 'array', ['array'], false, false, 'required', null],
            'array_keys:array' => ['array_keys', 0, 3, 'array', ['array'], false, false, 'required', null],
            'array_keys:filter_value' => ['array_keys', 1, 3, 'filter_value', ['mixed'], false, false, 'omitted', null],
            'array_keys:strict' => ['array_keys', 2, 3, 'strict', ['bool'], false, false, 'constant', false],
            'array_merge:arrays' => ['array_merge', 0, 1, 'arrays', ['array'], false, true, 'required', null],
            'in_array:needle' => ['in_array', 0, 3, 'needle', ['mixed'], false, false, 'required', null],
            'in_array:haystack' => ['in_array', 1, 3, 'haystack', ['array'], false, false, 'required', null],
            'in_array:strict' => ['in_array', 2, 3, 'strict', ['bool'], false, false, 'constant', false],
            'array_key_exists:key' => ['array_key_exists', 0, 2, 'key', ['mixed'], false, false, 'required', null],
            'array_key_exists:array' => ['array_key_exists', 1, 2, 'array', ['array'], false, false, 'required', null],
            'array_map:callback' => ['array_map', 0, 3, 'callback', ['callable', 'null'], false, false, 'required', null],
            'array_map:array' => ['array_map', 1, 3, 'array', ['array'], false, false, 'required', null],
            'array_map:arrays' => ['array_map', 2, 3, 'arrays', ['array'], false, true, 'required', null],
            'array_filter:array' => ['array_filter', 0, 3, 'array', ['array'], false, false, 'required', null],
            'array_filter:callback' => ['array_filter', 1, 3, 'callback', ['callable', 'null'], false, false, 'constant', null],
            'array_filter:mode' => ['array_filter', 2, 3, 'mode', ['int'], false, false, 'constant', 0],
            'array_reduce:array' => ['array_reduce', 0, 3, 'array', ['array'], false, false, 'required', null],
            'array_reduce:callback' => ['array_reduce', 1, 3, 'callback', ['callable'], false, false, 'required', null],
            'array_reduce:initial' => ['array_reduce', 2, 3, 'initial', ['mixed'], false, false, 'constant', null],
            'sort:array' => ['sort', 0, 2, 'array', ['array'], true, false, 'required', null],
            'sort:flags' => ['sort', 1, 2, 'flags', ['int'], false, false, 'constant', 0],
        ];
    }

    public function testModelNormalizesQualifiedCaseInsensitiveBuiltinsAndRejectsUnknownNames(): void
    {
        $library = new Library();
        $model = $library->model('\\STRLEN');
        self::assertNotNull($model);
        self::assertSame('php.strlen', $model->descriptor()->id);
        self::assertNull($library->model('unregistered_function'));
        self::assertNull($library->parameters('unregistered_function'));
        self::assertSame([], $library->parameters('time'));
    }
}
