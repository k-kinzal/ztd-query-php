<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Value\Arithmetic::class)]
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
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
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
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ArithmeticTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testApplyPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$s="";for($i=0;$i<3;$i++){$s.="x";}return $s;}');
        self::assertSame('xxx', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testNumberRejectsNonNumericStrings(): void
    {
        self::assertNull((new \Deriver\Internal\Value\Arithmetic())->number('not numeric'));
        self::assertSame(42, (new \Deriver\Internal\Value\Arithmetic())->number('42'));
    }

    public function testCalculateHandlesOversizedShifts(): void
    {
        self::assertSame(0, (new \Deriver\Internal\Value\Arithmetic())->calculate('<<', 1, 64));
        self::assertSame(-1, (new \Deriver\Internal\Value\Arithmetic())->calculate('>>', -1, 64));
    }

    public function testWarningTracksIntegralOperatorsButNotStringBitwiseOperations(): void
    {
        $operation = new \Deriver\Internal\Value\Arithmetic();
        self::assertTrue($operation->warning('%', \Deriver\Value\Term::constant(7), \Deriver\Value\Term::constant(1e30)));
        self::assertFalse($operation->warning('+', \Deriver\Value\Term::constant(7), \Deriver\Value\Term::constant(1e30)));
        self::assertFalse($operation->warning('&', \Deriver\Value\Term::constant('1.5'), \Deriver\Value\Term::constant('1')));
    }

    /**
     * @param string $operator
     * @param scalar|null $left
     * @param scalar|null $right
     * @param scalar|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerApplyOperators')]
    public function testApplyEvaluatesScalarOperatorsWithoutLosingSecrecy(string $operator, int|float|string|bool|null $left, int|float|string|bool|null $right, int|float|string|bool|null $expected): void
    {
        $result = (new \Deriver\Internal\Value\Arithmetic())->apply($operator, \Deriver\Value\Term::constant($left), \Deriver\Value\Term::constant($right, true));
        self::assertSame($expected, $result->native());
        self::assertTrue($result->isSecret());
    }

    /**
     * @return list<array{string, scalar|null, scalar|null, scalar|null}>
     */
    public static function providerApplyOperators(): array
    {
        return [
            ['+', 2, 3, 5], ['-', 2, 3, -1], ['*', 2, 3, 6], ['/', 7, 2, 3.5], ['%', -7, 3, -1], ['**', 2, 3, 8],
            ['&', 6, 3, 2], ['|', 6, 3, 7], ['^', 6, 3, 5], ['<<', 3, 2, 12], ['>>', 8, 2, 2],
            ['<<', 1, 63, -9223372036854775807 - 1], ['<<', 1, 64, 0], ['>>', 1, 64, 0], ['>>', -2, 64, -1],
            ['&', 'ab', 'XY', '@@'], ['|', 'ab', 'XY', 'y{'], ['^', 'ab', 'XY', '9;'],
            ['xor', 1, 0, true], ['xor', 1, 2, false], ['+', true, null, 1], ['+', 1.5, 2.0, 3.5], ['+', '12', '0.5', 12.5],
        ];
    }

    /**
     * @param string $operator
     * @param scalar|null $left
     * @param scalar|null $right
     * @param string $exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerApplyErrors')]
    public function testApplyReturnsTargetExceptionsForInvalidOperands(string $operator, int|float|string|bool|null $left, int|float|string|bool|null $right, string $exception): void
    {
        $result = (new \Deriver\Internal\Value\Arithmetic())->apply($operator, \Deriver\Value\Term::constant($left), \Deriver\Value\Term::constant($right));
        self::assertSame(['throwable', $exception], [$result->kind, $result->literal]);
    }

    /**
     * @return list<array{string, scalar|null, scalar|null, string}>
     */
    public static function providerApplyErrors(): array
    {
        return [['+', 'bad', 1, 'TypeError'], ['+', 1, 'bad', 'TypeError'], ['/', 1, 0, 'DivisionByZeroError'], ['%', 1, 0.5, 'DivisionByZeroError'], ['<<', 1, -1, 'ArithmeticError'], ['>>', 1, -1, 'ArithmeticError']];
    }

    public function testApplyKeepsAnUnknownOperatorAsAnExplicitBoundary(): void
    {
        $left = \Deriver\Value\Term::constant(2);
        $right = \Deriver\Value\Term::constant(3);
        $result = (new \Deriver\Internal\Value\Arithmetic())->apply('unsupported', $left, $right);
        self::assertSame(['opaque', 'UNSUPPORTED_LANGUAGE_FEATURE', [$left, $right]], [$result->kind, $result->literal, $result->operands]);
    }

    public function testNumberDistinguishesValidZeroFromInvalidNumericInput(): void
    {
        $arithmetic = new \Deriver\Internal\Value\Arithmetic();
        self::assertSame(0, $arithmetic->number(null));
        self::assertSame(0, $arithmetic->number(false));
        self::assertSame(1, $arithmetic->number(true));
        self::assertSame(2, $arithmetic->number(2));
        self::assertSame(2.5, $arithmetic->number(2.5));
        self::assertSame(0, $arithmetic->number('0'));
        self::assertNull($arithmetic->number(''));
    }

    public function testWarningDetectsEitherOperandAndSkipsSymbolicOrInvalidConversions(): void
    {
        $arithmetic = new \Deriver\Internal\Value\Arithmetic();
        self::assertTrue($arithmetic->warning('%', \Deriver\Value\Term::constant(1.5), \Deriver\Value\Term::constant(2)));
        self::assertTrue($arithmetic->warning('<<', \Deriver\Value\Term::constant(2), \Deriver\Value\Term::constant(1.5)));
        self::assertFalse($arithmetic->warning('^', \Deriver\Value\Term::constant(1), \Deriver\Value\Term::constant(2)));
        self::assertFalse($arithmetic->warning('%', \Deriver\Value\Term::parameter('n'), \Deriver\Value\Term::constant(2)));
        self::assertFalse($arithmetic->warning('%', \Deriver\Value\Term::constant(2), \Deriver\Value\Term::parameter('n')));
        self::assertFalse($arithmetic->warning('%', \Deriver\Value\Term::constant('bad'), \Deriver\Value\Term::constant(2)));
        self::assertFalse($arithmetic->warning('%', \Deriver\Value\Term::constant(2), \Deriver\Value\Term::constant('bad')));
    }
}
