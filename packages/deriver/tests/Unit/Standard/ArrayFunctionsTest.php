<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use Deriver\Standard\ArrayFunctions;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayFunctions::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
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
#[UsesClass(\Deriver\Internal\IR\Program::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
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
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
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
#[UsesClass(ArrayFunctions::class)]
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Standard\ScalarFunctions::class)]
#[UsesClass(\Deriver\Standard\TypePredicates::class)]
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
