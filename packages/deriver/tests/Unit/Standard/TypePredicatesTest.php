<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use Deriver\Standard\TypePredicates;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypePredicates::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
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
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Standard\ScalarFunctions::class)]
#[UsesClass(TypePredicates::class)]
#[UsesClass(Term::class)]
#[Small]
final class TypePredicatesTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return [is_int(1),is_string(1),is_array([]),is_null(null)];}');
        self::assertSame([true, false, true, true], $result->normalOutcomes[0]->values['return']->native());
    }
    public function testBoundSeparatesKnownTypeFactsFromMixedUnions(): void
    {
        $predicates = new TypePredicates();
        self::assertTrue($predicates->bound('is_array', new Term('external', 'env', attributes:['type' => 'array'])));
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
        yield 'string true' => ['is_string',Term::constant('text'),true];
        yield 'string false' => ['is_string',Term::constant(4),false];
        yield 'integer true' => ['is_int',Term::constant(4),true];
        yield 'integer false' => ['is_int',Term::constant(4.0),false];
        yield 'integer alias true' => ['is_integer',Term::constant(4),true];
        yield 'integer alias false' => ['is_integer',Term::constant('4'),false];
        yield 'float true' => ['is_float',Term::constant(4.0),true];
        yield 'float false' => ['is_float',Term::constant(4),false];
        yield 'double alias true' => ['is_double',Term::constant(4.0),true];
        yield 'double alias false' => ['is_double',Term::constant('4.0'),false];
        yield 'boolean true' => ['is_bool',Term::constant(false),true];
        yield 'boolean false' => ['is_bool',Term::constant(0),false];
        yield 'null true' => ['is_null',Term::constant(null),true];
        yield 'null false' => ['is_null',Term::constant(false),false];
        yield 'numeric integer' => ['is_numeric',Term::constant(4),true];
        yield 'numeric string' => ['is_numeric',Term::constant(' 4.5 '),true];
        yield 'numeric false' => ['is_numeric',Term::constant('4suffix'),false];
        yield 'scalar integer' => ['is_scalar',Term::constant(4),true];
        yield 'scalar null' => ['is_scalar',Term::constant(null),false];
        yield 'array false on scalar' => ['is_array',Term::constant(4),false];
        yield 'object false on scalar' => ['is_object',Term::constant(4),false];
        yield 'closed array' => ['is_array',Term::array([]),true];
        yield 'open array' => ['is_array',Term::array([], true),true];
        yield 'array is not object' => ['is_object',Term::array([]),false];
        yield 'object' => ['is_object',new Term('object', 'one'),true];
        yield 'closure object' => ['is_object',new Term('closure', 'body'),true];
        yield 'enum object' => ['is_object',new Term('enum', 'Mode::Ready'),true];
        yield 'object is not array' => ['is_array',new Term('object', 'one'),false];
        yield 'object is not scalar' => ['is_scalar',new Term('object', 'one'),false];
        yield 'array is not scalar' => ['is_scalar',Term::array([]),false];
        yield 'closure callable' => ['is_callable',new Term('closure', 'body'),true];
        yield 'function callable' => ['is_callable',new Term('callable', 'function'),true];
        yield 'method callable' => ['is_callable',new Term('callable-method', 'method'),true];
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
        yield 'int' => ['is_int','int',true];
        yield 'integer alias' => ['is_integer','int',true];
        yield 'float' => ['is_float','float',true];
        yield 'double alias' => ['is_double','float',true];
        yield 'string' => ['is_string','string',true];
        yield 'bool' => ['is_bool','bool',true];
        yield 'true' => ['is_bool','true',true];
        yield 'false' => ['is_bool','false',true];
        yield 'boolean union' => ['is_bool','true|false',true];
        yield 'null' => ['is_null','null',true];
        yield 'array' => ['is_array','array',true];
        yield 'object' => ['is_object','object',true];
        yield 'scalar union' => ['is_scalar','int|float|string|bool|true|false',true];
        yield 'numeric union' => ['is_numeric','int|float',true];
        yield 'disjoint union' => ['is_int','string|float|bool|null|array|object',false];
        yield 'non scalar union' => ['is_scalar','array|object|null',false];
        yield 'non numeric union' => ['is_numeric','bool|false|null',false];
        yield 'mixed truth union' => ['is_int','int|string',null];
        yield 'numeric string unknown' => ['is_numeric','string',null];
        yield 'numeric string union unknown' => ['is_numeric','int|string',null];
        yield 'mixed type' => ['is_array','mixed',null];
        yield 'class type' => ['is_object','Box',null];
        yield 'unknown union arm' => ['is_int','int|Box',null];
        yield 'unsupported predicate' => ['is_resource','int',null];
        yield 'empty type' => ['is_int','',null];
    }

    public function testBoundRejectsMalformedNonStringTypeMetadata(): void
    {
        self::assertNull((new TypePredicates())->bound('is_int', new Term('external', 'input', attributes:['type' => 7])));
    }

    public function testApplyUsesProvenTypeBoundsAndRetainsConfidentiality(): void
    {
        $input = new Term('parameter', 'input', attributes:['type' => 'int|float'], secret:true);
        $result = (new TypePredicates())->apply('is_numeric', $input);
        self::assertTrue($result->native());
        self::assertTrue($result->isSecret());
    }

    /**
     * @param string $name Predicate
     * @param Term $input Input whose truth depends on runtime data or declarations
     */
    #[DataProvider('providerUnresolvedPredicates')]
    public function testApplyRetainsUnknownPredicatesWithTheirInputAndBooleanType(string $name, Term $input): void
    {
        $result = (new TypePredicates())->apply($name, $input);
        self::assertSame('intrinsic', $result->kind);
        self::assertSame($name, $result->literal);
        self::assertSame([$input], $result->operands);
        self::assertSame('bool', $result->attributes['type']);
    }

    /**
     * @return iterable<string,array{string,Term}>
     */
    public static function providerUnresolvedPredicates(): iterable
    {
        yield 'mixed parameter' => ['is_int',Term::parameter('input')];
        yield 'string numeric content' => ['is_numeric',Term::parameter('input', 'string')];
        yield 'class callable protocol' => ['is_callable',new Term('object', 'box', attributes:['class' => 'Box'])];
        yield 'named function existence' => ['is_callable',Term::constant('function_name')];
        yield 'callback array binding' => ['is_callable',Term::fromNative(['Box','run'])];
    }
}
