<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Scalar;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\LoopConvergence;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Cell;
use Deriver\Deriver\Evaluator\Execution\Demand\Components;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Demand\Key;
use Deriver\Deriver\Evaluator\Execution\Demand\Table;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\CompoundAssignment;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Increment;
use Deriver\Deriver\Evaluator\Scalar\IntegerConversion;
use Deriver\Deriver\Evaluator\Scalar\NumericString;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Control\LoopLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\EffectInspection;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Arithmetic::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
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
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
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
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(LoopLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
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
    public function testApplyPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){$s="";for($i=0;$i<3;$i++){$s.="x";}return $s;}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
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
        self::assertTrue($operation->warning('%', Term::constant(7), Term::constant(1.0E+30)));
        self::assertFalse($operation->warning('+', Term::constant(7), Term::constant(1.0E+30)));
        self::assertFalse($operation->warning('&', Term::constant('1.5'), Term::constant('1')));
    }
    /**
     * @param string $operator
     * @param scalar|null $left
     * @param scalar|null $right
     * @param scalar|null $expected
     */
    #[DataProvider('providerApplyOperators')]
    public function testApplyEvaluatesScalarOperators(string $operator, int|float|string|bool|null $left, int|float|string|bool|null $right, int|float|string|bool|null $expected): void
    {
        $result = (new Arithmetic())->apply($operator, Term::constant($left), Term::constant($right));
        self::assertSame($expected, $result->native());
    }
    /**
     * @return list<array{string, scalar|null, scalar|null, scalar|null}>
     */
    public static function providerApplyOperators(): array
    {
        return [['+', 2, 3, 5], ['-', 2, 3, -1], ['*', 2, 3, 6], ['/', 7, 2, 3.5], ['%', -7, 3, -1], ['**', 2, 3, 8], ['&', 6, 3, 2], ['|', 6, 3, 7], ['^', 6, 3, 5], ['<<', 3, 2, 12], ['>>', 8, 2, 2], ['<<', 1, 63, -9223372036854775807 - 1], ['<<', 1, 64, 0], ['>>', 1, 64, 0], ['>>', -2, 64, -1], ['&', 'ab', 'XY', '@@'], ['|', 'ab', 'XY', 'y{'], ['^', 'ab', 'XY', '9;'], ['xor', 1, 0, true], ['xor', 1, 2, false], ['+', true, null, 1], ['+', 1.5, 2.0, 3.5], ['+', '12', '0.5', 12.5]];
    }
    /**
     * @param string $operator
     * @param scalar|null $left
     * @param scalar|null $right
     * @param string $exception
     */
    #[DataProvider('providerApplyErrors')]
    public function testApplyReturnsTargetExceptionsForInvalidOperands(string $operator, int|float|string|bool|null $left, int|float|string|bool|null $right, string $exception): void
    {
        $result = (new Arithmetic())->apply($operator, Term::constant($left), Term::constant($right));
        self::assertSame(['throwable', $exception], [$result->kind->value, $result->literal]);
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
        self::assertSame(['opaque', 'UNSUPPORTED_LANGUAGE_FEATURE', [$left, $right]], [$result->kind->value, $result->literal, $result->operands]);
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
    /**
     * @param int|float $base
     * @param int|float $exponent
     */
    #[DataProvider('providerZeroBasePowers')]
    public function testPowerOfZeroToANegativeExponentIsASignedInfinityWithoutHostDiagnostics(int|float $base, int|float $exponent, float $expected): void
    {
        self::assertSame($expected, (new Arithmetic())->calculate('**', $base, $exponent));
    }
    /**
     * @return array<string, array{int|float, int|float, float}>
     */
    public static function providerZeroBasePowers(): array
    {
        return ['integer zero' => [0, -1, INF], 'positive zero with fraction' => [0.0, -0.5, INF], 'negative zero with odd exponent' => [-0.0, -3, -INF], 'negative zero with even exponent' => [-0.0, -2, INF], 'negative zero with fraction' => [-0.0, -2.5, INF], 'negative zero with negative infinity' => [-0.0, -INF, INF], 'negative zero with minimum integer' => [-0.0, PHP_INT_MIN, INF]];
    }
    public function testXorTreatsNanAsTrueWithoutHostDiagnostics(): void
    {
        self::assertTrue((new Arithmetic())->apply('xor', Term::constant(NAN), Term::constant(0))->native());
    }
}
