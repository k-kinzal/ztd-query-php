<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Api;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Api\ResultAssessment::class)]
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
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $assessment = new \Deriver\Internal\Api\ResultAssessment();
        self::assertTrue($assessment->opaque(\Deriver\Value\Term::array(['field' => \Deriver\Value\Term::opaque('MISSING_CALL_MODEL')])));
        self::assertFalse($assessment->opaque(\Deriver\Value\Term::parameter('input')));
    }
    public function testPrecisionRecognizesAbstractFactsWithoutASeparateFrontier(): void
    {
        $assessment = new \Deriver\Internal\Api\ResultAssessment();
        $value = \Deriver\Value\Term::array([new \Deriver\Value\Term('abstract', 'WIDENED', attributes:['type' => 'int'])]);
        self::assertSame('abstract', $assessment->precision($value));
        self::assertSame('opaque', $assessment->precision(\Deriver\Value\Term::array([$value,\Deriver\Value\Term::opaque('MISSING')])));
    }
    public function testPrecisionPreservesSharedGraphComplexity(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        self::assertSame('exact-symbolic', (new \Deriver\Internal\Api\ResultAssessment())->precision($value));
    }
    public function testPrecisionIncludesReturnedObjectStorageAndTerminatesAtCycles(): void
    {
        $object = new \Deriver\Value\Term('object', 'a');
        $storage = new \Deriver\Api\Result\StorageSnapshot(cells: ['object:a' => \Deriver\Value\Term::array(['self' => $object, 'value' => \Deriver\Value\Term::opaque('MISSING_CALL_MODEL')])]);
        self::assertSame('opaque', (new \Deriver\Internal\Api\ResultAssessment())->precision($object, $storage));
        self::assertSame('exact-symbolic', (new \Deriver\Internal\Api\ResultAssessment())->precision($object, new \Deriver\Api\Result\StorageSnapshot(cells: ['object:a' => \Deriver\Value\Term::array(['self' => $object])])));
    }
    public function testAssessIncludesExceptionalValuesAndTheirReachableStorage(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->exceptional[] = new \Deriver\Api\Result\Exceptional(\Deriver\Value\Term::opaque('UNRESOLVED_THROW'));
        $assessment = (new \Deriver\Internal\Api\ResultAssessment())->assess($context);
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
        $context->normal[] = new \Deriver\Api\Result\Alternative(['value' => \Deriver\Value\Term::constant(1)]);
        $context->frontiers['boundary'] = new \Deriver\Api\Result\Frontier($code, new \Deriver\Api\Reference\SourceRef('snapshot', 'a.php', 0, 1), 'operation');
        $assessment = (new \Deriver\Internal\Api\ResultAssessment())->assess($context);
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
        $context->normal = [new \Deriver\Api\Result\Alternative(['first' => new \Deriver\Value\Term('abstract', 'WIDENED')]), new \Deriver\Api\Result\Alternative(['second' => \Deriver\Value\Term::parameter('input')])];
        $assessment = new \Deriver\Internal\Api\ResultAssessment();
        $abstract = $assessment->assess($context);
        $context->normal[] = new \Deriver\Api\Result\Alternative(['third' => \Deriver\Value\Term::opaque('UNRESOLVED')]);
        $opaque = $assessment->assess($context);
        $context->normal = array_reverse($context->normal);
        $reversed = $assessment->assess($context);
        self::assertSame(['abstract', 'opaque', 'opaque'], [$abstract->precision, $opaque->precision, $reversed->precision]);
        self::assertSame('not-enumerated', $abstract->enumeration);
    }

    public function testAssessIncludesAbstractExceptionsInTheSharedPrecision(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $context->normal[] = new \Deriver\Api\Result\Alternative(['value' => \Deriver\Value\Term::constant(1)]);
        $context->exceptional[] = new \Deriver\Api\Result\Exceptional(new \Deriver\Value\Term('abstract', 'throwable-range'));
        $assessment = (new \Deriver\Internal\Api\ResultAssessment())->assess($context);
        self::assertSame('abstract', $assessment->precision);
        self::assertSame('not-enumerated', $assessment->enumeration);
    }

    public function testPrecisionFollowsReferenceCellsAndAbstractModelSlots(): void
    {
        $assessment = new \Deriver\Internal\Api\ResultAssessment();
        $storage = new \Deriver\Api\Result\StorageSnapshot(cells: ['shared' => new \Deriver\Value\Term('abstract', 'WIDENED'), 'model:object' => \Deriver\Value\Term::array(['slot' => \Deriver\Value\Term::opaque('MISSING')])]);
        self::assertSame('abstract', $assessment->precision(new \Deriver\Value\Term('cell', 'shared'), $storage));
        self::assertSame('opaque', $assessment->precision(new \Deriver\Value\Term('object', 'object'), $storage));
        self::assertSame('exact-symbolic', $assessment->precision(new \Deriver\Value\Term('object', 'untracked'), $storage));
        self::assertSame('exact-symbolic', $assessment->precision(\Deriver\Value\Term::constant('shared'), $storage));
        self::assertSame('opaque', $assessment->precision(new \Deriver\Value\Term('uninitialized')));
    }
}
