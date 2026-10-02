<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\Library;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Library::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\ArrayFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\ScalarFunctions::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
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
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\Term::class)]
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
            'array_shift:array' => ['array_shift', 0, 1, 'array', ['array'], true, false, 'required', null],
            'array_pop:array' => ['array_pop', 0, 1, 'array', ['array'], true, false, 'required', null],
            'array_push:array' => ['array_push', 0, 2, 'array', ['array'], true, false, 'required', null],
            'array_push:values' => ['array_push', 1, 2, 'values', ['mixed'], false, true, 'required', null],
            'array_unshift:array' => ['array_unshift', 0, 2, 'array', ['array'], true, false, 'required', null],
            'array_unshift:values' => ['array_unshift', 1, 2, 'values', ['mixed'], false, true, 'required', null],
            'array_key_first:array' => ['array_key_first', 0, 1, 'array', ['array'], false, false, 'required', null],
            'array_key_last:array' => ['array_key_last', 0, 1, 'array', ['array'], false, false, 'required', null],
            'array_slice:array' => ['array_slice', 0, 4, 'array', ['array'], false, false, 'required', null],
            'array_slice:offset' => ['array_slice', 1, 4, 'offset', ['int'], false, false, 'required', null],
            'array_slice:length' => ['array_slice', 2, 4, 'length', ['int', 'null'], false, false, 'constant', null],
            'array_slice:preserve_keys' => ['array_slice', 3, 4, 'preserve_keys', ['bool'], false, false, 'constant', false],
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
    public function testValueFunctionsUsePhpParameterNames(): void
    {
        $parameters = (new Library())->valueFunctions('str_repeat');
        self::assertNotNull($parameters);
        self::assertSame(['string','times'], array_column($parameters, 'name'));
    }

}
