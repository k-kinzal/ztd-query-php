<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\ResultAssessment;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use Deriver\Result\Frontier;
use Deriver\Result\StorageSnapshot;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(Exceptional::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
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
