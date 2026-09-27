<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\Arithmetic;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arithmetic::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
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
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
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
        self::assertNull((new Arithmetic())->number('not numeric'));
        self::assertSame(42, (new Arithmetic())->number('42'));
    }

    public function testCalculateHandlesOversizedShifts(): void
    {
        self::assertSame(0, (new Arithmetic())->calculate('<<', 1, 64));
        self::assertSame(-1, (new Arithmetic())->calculate('>>', -1, 64));
    }

    public function testWarningTracksIntegralOperatorsButNotStringBitwiseOperations(): void
    {
        $operation = new Arithmetic();
        self::assertTrue($operation->warning('%', Term::constant(7), Term::constant(1e30)));
        self::assertFalse($operation->warning('+', Term::constant(7), Term::constant(1e30)));
        self::assertFalse($operation->warning('&', Term::constant('1.5'), Term::constant('1')));
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
        $result = (new Arithmetic())->apply($operator, Term::constant($left), Term::constant($right, true));
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
        $result = (new Arithmetic())->apply($operator, Term::constant($left), Term::constant($right));
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
        $left = Term::constant(2);
        $right = Term::constant(3);
        $result = (new Arithmetic())->apply('unsupported', $left, $right);
        self::assertSame(['opaque', 'UNSUPPORTED_LANGUAGE_FEATURE', [$left, $right]], [$result->kind, $result->literal, $result->operands]);
    }

    public function testNumberDistinguishesValidZeroFromInvalidNumericInput(): void
    {
        $arithmetic = new Arithmetic();
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
        $arithmetic = new Arithmetic();
        self::assertTrue($arithmetic->warning('%', Term::constant(1.5), Term::constant(2)));
        self::assertTrue($arithmetic->warning('<<', Term::constant(2), Term::constant(1.5)));
        self::assertFalse($arithmetic->warning('^', Term::constant(1), Term::constant(2)));
        self::assertFalse($arithmetic->warning('%', Term::parameter('n'), Term::constant(2)));
        self::assertFalse($arithmetic->warning('%', Term::constant(2), Term::parameter('n')));
        self::assertFalse($arithmetic->warning('%', Term::constant('bad'), Term::constant(2)));
        self::assertFalse($arithmetic->warning('%', Term::constant(2), Term::constant('bad')));
    }
}
