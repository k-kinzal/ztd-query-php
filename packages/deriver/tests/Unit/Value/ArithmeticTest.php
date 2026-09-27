<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\LoopConvergence;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CompoundAssignment;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
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
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\LoopLowering;
use Deriver\Source\Compilation\EffectInspection;
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
use Deriver\Value\Arithmetic;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\Increment;
use Deriver\Value\IntegerConversion;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arithmetic::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(LoopConvergence::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CompoundAssignment::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
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
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(LoopLowering::class)]
#[UsesClass(EffectInspection::class)]
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
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(IntegerConversion::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
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
