<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Model\Builtin\ArrayFunctions;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayFunctions::class)]
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
#[UsesClass(Arguments::class)]
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
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Builtin\ScalarFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\TypePredicates::class)]
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
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
