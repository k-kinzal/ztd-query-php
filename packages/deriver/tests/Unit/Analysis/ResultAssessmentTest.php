<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyMagic;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Builtin\Library;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
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
use Deriver\Result\Exceptional;
use Deriver\Result\Frontier;
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
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
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
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResultAssessment::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyMagic::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Library::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(Exceptional::class)]
#[UsesClass(Frontier::class)]
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
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
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
final class ResultAssessmentTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAssessPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class Box{public $value="known";} function target(){$box=new Box; $local="unrelated"; externalMutation($box);return [$box->value,$local];}');
        self::assertSame('open', $result->assessment->closure);
        self::assertSame('MISSING_CALL_MODEL', $result->frontiers[0]->code);
        self::assertSame('unrelated', $result->normalOutcomes[0]->values['return']->operands[1]->native());
        self::assertFalse($result->normalOutcomes[0]->values['return']->operands[0]->isConcrete());
    }
    public function testOpaqueDetectsUnknownFieldsWithoutTreatingSymbolicInputsAsErrors(): void
    {
        $assessment = new ResultAssessment();
        self::assertTrue($assessment->opaque(Term::array(['field' => Term::opaque('MISSING_CALL_MODEL')])));
        self::assertFalse($assessment->opaque(Term::parameter('input')));
    }
    public function testPrecisionRecognizesAbstractFactsWithoutASeparateFrontier(): void
    {
        $assessment = new ResultAssessment();
        $value = Term::array([new Term('abstract', 'WIDENED', attributes:['type' => 'int'])]);
        self::assertSame('abstract', $assessment->precision($value));
        self::assertSame('opaque', $assessment->precision(Term::array([$value,Term::opaque('MISSING')])));
    }
    public function testPrecisionPreservesSharedGraphComplexity(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        self::assertSame('exact-symbolic', (new ResultAssessment())->precision($value));
    }
    public function testPrecisionIncludesReturnedObjectStorageAndTerminatesAtCycles(): void
    {
        $object = new Term('object', 'a');
        $storage = new StorageSnapshot(cells: ['object:a' => Term::array(['self' => $object, 'value' => Term::opaque('MISSING_CALL_MODEL')])]);
        self::assertSame('opaque', (new ResultAssessment())->precision($object, $storage));
        self::assertSame('exact-symbolic', (new ResultAssessment())->precision($object, new StorageSnapshot(cells: ['object:a' => Term::array(['self' => $object])])));
    }
    public function testAssessIncludesExceptionalValuesAndTheirReachableStorage(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->exceptional[] = new Exceptional(Term::opaque('UNRESOLVED_THROW'));
        $assessment = (new ResultAssessment())->assess($context);
        self::assertSame('opaque', $assessment->precision);
        self::assertSame('not-enumerated', $assessment->enumeration);
    }

    /**
     * @param string $code
     * @param array{string, string, string, string, string} $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFrontierAssessments')]
    public function testAssessKeepsTheQualityAxesIndependentForEachBoundary(string $code, array $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->normal[] = new Alternative(['value' => Term::constant(1)]);
        $context->frontiers['boundary'] = new Frontier($code, new SourceRef('snapshot', 'a.php', 0, 1), 'operation');
        $assessment = (new ResultAssessment())->assess($context);
        self::assertSame($expected, [$assessment->closure, $assessment->precision, $assessment->correlation, $assessment->coverage, $assessment->enumeration]);
    }

    /**
     * @return list<array{string, array{string, string, string, string, string}}>
     */
    public static function providerFrontierAssessments(): array
    {
        return [
            ['EXTERNAL_INPUT', ['closed', 'exact-symbolic', 'preserved', 'over-approximation', 'finite-exhaustive']],
            ['PHP_WARNING', ['closed', 'exact-symbolic', 'preserved', 'over-approximation', 'finite-exhaustive']],
            ['WIDENED', ['closed', 'abstract', 'preserved', 'over-approximation', 'finite-exhaustive']],
            ['MISSING_CALL_MODEL', ['open', 'exact-symbolic', 'preserved', 'over-approximation', 'not-enumerated']],
            ['CORRELATION_RELAXED', ['open', 'exact-symbolic', 'relaxed', 'over-approximation', 'not-enumerated']],
            ['UNSUPPORTED_LANGUAGE_FEATURE', ['open', 'exact-symbolic', 'preserved', 'unavailable', 'not-enumerated']],
            ['UNSPECIFIED_EVALUATION_ORDER', ['open', 'exact-symbolic', 'preserved', 'unavailable', 'not-enumerated']],
        ];
    }

    public function testAssessClassifiesNormalAbstractAndOpaqueAlternativesWithoutOrderDependence(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->normal = [new Alternative(['first' => new Term('abstract', 'WIDENED')]), new Alternative(['second' => Term::parameter('input')])];
        $assessment = new ResultAssessment();
        $abstract = $assessment->assess($context);
        $context->normal[] = new Alternative(['third' => Term::opaque('UNRESOLVED')]);
        $opaque = $assessment->assess($context);
        $context->normal = array_reverse($context->normal);
        $reversed = $assessment->assess($context);
        self::assertSame(['abstract', 'opaque', 'opaque'], [$abstract->precision, $opaque->precision, $reversed->precision]);
        self::assertSame('not-enumerated', $abstract->enumeration);
    }

    public function testAssessIncludesAbstractExceptionsInTheSharedPrecision(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->normal[] = new Alternative(['value' => Term::constant(1)]);
        $context->exceptional[] = new Exceptional(new Term('abstract', 'throwable-range'));
        $assessment = (new ResultAssessment())->assess($context);
        self::assertSame('abstract', $assessment->precision);
        self::assertSame('not-enumerated', $assessment->enumeration);
    }

    public function testPrecisionFollowsReferenceCellsAndAbstractModelSlots(): void
    {
        $assessment = new ResultAssessment();
        $storage = new StorageSnapshot(cells: ['shared' => new Term('abstract', 'WIDENED'), 'model:object' => Term::array(['slot' => Term::opaque('MISSING')])]);
        self::assertSame('abstract', $assessment->precision(new Term('cell', 'shared'), $storage));
        self::assertSame('opaque', $assessment->precision(new Term('object', 'object'), $storage));
        self::assertSame('exact-symbolic', $assessment->precision(new Term('object', 'untracked'), $storage));
        self::assertSame('exact-symbolic', $assessment->precision(Term::constant('shared'), $storage));
        self::assertSame('opaque', $assessment->precision(new Term('uninitialized')));
    }
}
