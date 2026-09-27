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
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayFunctions::class)]
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
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(TypePredicates::class)]
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
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ArrayFunctionsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return array_merge([2=>"left","x"=>1],[2=>"right","x"=>2]);}');
        self::assertSame([0 => 'left', 'x' => 2, 1 => 'right'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testCountIncludesNestedElementsOnlyInRecursiveMode(): void
    {
        $array = Term::fromNative(['a' => [1, [2]], 'b' => 3]);
        $functions = new ArrayFunctions();
        self::assertSame(2, $functions->count($array, false)->native());
        self::assertSame(5, $functions->count($array, true)->native());
    }
    public function testMembershipDistinguishesStrictAndLooseComparison(): void
    {
        $values = [Term::constant('2'), Term::fromNative([2])];
        $functions = new ArrayFunctions();
        self::assertSame(true, $functions->membership('in_array', $values)->native());
        self::assertSame(false, $functions->membership('in_array', [...$values, Term::constant(true)])->native());
    }
    public function testKeysFiltersWithoutRenumberingSourceKeys(): void
    {
        $array = Term::fromNative(['first' => 2, 7 => '2', 'last' => 3]);
        $functions = new ArrayFunctions();
        self::assertSame(['first', 7], $functions->keys($array, [$array, Term::constant(2)])->native());
        self::assertSame(['first'], $functions->keys($array, [$array, Term::constant(2), Term::constant(true)])->native());
    }
    public function testKeyExistsIncludesNullValuesAndPreservesOpenRemainders(): void
    {
        $functions = new ArrayFunctions();
        self::assertSame(true, $functions->keyExists(Term::constant('a'), Term::fromNative(['a' => null]))->native());
        self::assertSame(false, $functions->keyExists(Term::constant('a'), Term::array([]))->native());
        self::assertSame('intrinsic', $functions->keyExists(Term::constant('a'), Term::array([], true))->kind);
    }

    /**
     * @param list<Term> $values Arguments after native binding
     */
    #[DataProvider('providerCountBoundaries')]
    public function testCountArgumentsPreservesInvalidModesAndUnknownProtocols(array $values, string $kind, mixed $literal): void
    {
        $result = (new ArrayFunctions())->countArguments($values);
        self::assertSame($kind, $result->kind);
        self::assertSame($literal, $result->literal);
    }

    /**
     * @return array<string,array{list<Term>,string,mixed}>
     */
    public static function providerCountBoundaries(): array
    {
        return [
            'unknown array normal' => [[Term::parameter('a', 'array')],'intrinsic','count'],
            'open array normal' => [[Term::array([], true)],'intrinsic','count'],
            'known recursive' => [[Term::fromNative([[1]]) ,Term::constant(1)],'constant',2],
            'invalid known mode' => [[Term::array([]),Term::constant(2)],'throwable','ValueError'],
            'invalid unknown array mode' => [[Term::parameter('a', 'array'),Term::constant(-1)],'throwable','ValueError'],
            'unknown mode' => [[Term::array([]),Term::parameter('mode', 'int')],'opaque','UNSUPPORTED_MODEL_CASE'],
            'unknown recursive array' => [[Term::parameter('a', 'array'),Term::constant(1)],'opaque','UNSUPPORTED_MODEL_CASE'],
            'countable protocol' => [[Term::parameter('a', 'Countable')],'opaque','UNSUPPORTED_MODEL_CASE'],
            'union protocol' => [[Term::parameter('a', 'array|Countable')],'opaque','UNSUPPORTED_MODEL_CASE'],
        ];
    }

    /**
     * @param list<Term> $values Arguments after native binding
     * @param mixed $expected Concrete result
     */
    #[DataProvider('providerArrayOperations')]
    public function testApplyPreservesKeyOrderAndComparisonModes(string $name, array $values, mixed $expected): void
    {
        self::assertSame($expected, (new ArrayFunctions())->apply($name, $values)->native());
    }

    /**
     * @return array<string,array{string,list<Term>,mixed}>
     */
    public static function providerArrayOperations(): array
    {
        return [
            'keys unfiltered' => ['array_keys',[Term::fromNative(['a' => null,8 => false])],['a',8]],
            'keys explicit null' => ['array_keys',[Term::fromNative(['a' => null,8 => false]),Term::constant(null),Term::constant(true)],['a']],
            'keys loose false' => ['array_keys',[Term::fromNative(['a' => null,8 => false]),Term::constant(false)],['a',8]],
            'values renumber' => ['array_values',[Term::fromNative(['a' => null,8 => false])],[null,false]],
            'merge variadic' => ['array_merge',[Term::fromNative([[7 => 'a','x' => 1],[9 => 'b','x' => 2]])],[0 => 'a','x' => 2,1 => 'b']],
            'merge empty' => ['array_merge',[Term::array([])],[]],
            'count normal' => ['count',[Term::fromNative([[1,2],3])],2],
            'count recursive' => ['count',[Term::fromNative([[1,2],3]),Term::constant(1)],4],
            'contains loose' => ['in_array',[Term::constant('2'),Term::fromNative([1,2,3])],true],
            'contains strict' => ['in_array',[Term::constant('2'),Term::fromNative([1,2,3]),Term::constant(true)],false],
            'contains later exact' => ['in_array',[Term::constant(2),Term::array([Term::parameter('x'),Term::constant(2)])],true],
            'contains absent' => ['in_array',[Term::constant(4),Term::fromNative([1,2,3])],false],
            'key integer normalization' => ['array_key_exists',[Term::constant('2'),Term::fromNative([2 => null])],true],
            'key leading zero' => ['array_key_exists',[Term::constant('02'),Term::fromNative([2 => null])],false],
            'key null normalization' => ['array_key_exists',[Term::constant(null),Term::fromNative(['' => null])],true],
        ];
    }

    /**
     * @param list<Term> $values Arguments after native binding
     */
    #[DataProvider('providerUnresolvedArrays')]
    public function testApplyRetainsUnresolvedArrayDependencies(string $name, array $values, string $kind, string $type): void
    {
        $result = (new ArrayFunctions())->apply($name, $values);
        self::assertSame($kind, $result->kind);
        self::assertSame($type, $result->attributes['type']);
        self::assertSame($values, $result->operands);
    }

    /**
     * @return array<string,array{string,list<Term>,string,string}>
     */
    public static function providerUnresolvedArrays(): array
    {
        return [
            'unknown values' => ['array_values',[Term::parameter('a', 'array')],'intrinsic','array'],
            'open keys' => ['array_keys',[Term::array([], true)],'intrinsic','array'],
            'unknown membership' => ['in_array',[Term::constant(1),Term::parameter('a', 'array')],'intrinsic','bool'],
            'open membership' => ['in_array',[Term::constant(1),Term::array([], true)],'intrinsic','bool'],
            'symbolic element' => ['in_array',[Term::constant(1),Term::array([Term::parameter('x')])],'intrinsic','bool'],
            'unknown strictness' => ['in_array',[Term::constant(1),Term::array([]),Term::parameter('strict', 'bool')],'intrinsic','bool'],
            'unknown key' => ['array_key_exists',[Term::parameter('key'),Term::array([])],'intrinsic','bool'],
            'unknown key filter' => ['array_keys',[Term::fromNative([1]),Term::parameter('filter')],'opaque','array'],
            'unknown keys strictness' => ['array_keys',[Term::array([]),Term::constant(1),Term::parameter('strict', 'bool')],'opaque','array'],
        ];
    }

    public function testCountRetainsUnknownNestedShapesAndConfidentialCounts(): void
    {
        $functions = new ArrayFunctions();
        self::assertSame('opaque', $functions->count(Term::array([Term::array([], true)]), true)->kind);
        self::assertSame('opaque', $functions->count(Term::array([Term::array([Term::parameter('nested')])]), true)->kind);
        self::assertSame(1, $functions->count(Term::array([new Term('object', 'identity')]), true)->native());
        self::assertTrue($functions->count(Term::fromNative([], true), false)->isSecret());
    }

    public function testKeyExistsRejectsIllegalAggregateKeys(): void
    {
        $result = (new ArrayFunctions())->keyExists(Term::array([]), Term::array([]));
        self::assertSame('throwable', $result->kind);
        self::assertSame('TypeError', $result->literal);
    }

}
