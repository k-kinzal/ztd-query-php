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
use Deriver\Evaluation\Transfer\ConstantTransfer;
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
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Builtin\ScalarFunctions;
use Deriver\Model\Builtin\TypePredicates;
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

#[CoversClass(TypePredicates::class)]
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
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
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
