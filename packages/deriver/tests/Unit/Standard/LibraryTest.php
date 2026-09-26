<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use Deriver\Standard\Library;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Library::class)]
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
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\NativeArguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\IntrinsicTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\CallModel::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(\Deriver\Model\ModelDescriptor::class)]
#[UsesClass(\Deriver\Model\Plan\Action::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[UsesClass(\Deriver\Model\Plan\SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\ArrayFunctions::class)]
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(\Deriver\Standard\ScalarFunctions::class)]
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
