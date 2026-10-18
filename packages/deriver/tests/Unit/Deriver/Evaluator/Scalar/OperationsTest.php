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
use Deriver\Deriver\Evaluator\Execution\Context;
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
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
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
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\FloatConversion;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\IntegerConversion;
use Deriver\Deriver\Evaluator\Scalar\NumericString;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AggregateLowering;
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
#[CoversClass(Operations::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
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
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
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
#[UsesClass(AggregateLowering::class)]
#[UsesClass(Lowerer::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(FloatConversion::class)]
#[UsesClass(Identity::class)]
#[UsesClass(IntegerConversion::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Term::class)]
#[Small]
final class OperationsTest extends TestCase
{
    public function testTruthPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){return [2=>"left","x"=>1]+[2=>"right",3=>"new"];}';
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
        self::assertSame([2 => 'left', 'x' => 1, 3 => 'new'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testTruthPreservesUnknownArrayEmptiness(): void
    {
        self::assertNull((new Operations())->truth(Term::array([], true)));
        self::assertFalse((new Operations())->truth(Term::constant('0')));
    }
    public function testCastPreservesSymbolicInputs(): void
    {
        $input = Term::parameter('id', 'int');
        $result = (new Operations())->cast('string', $input);
        self::assertSame(TermKind::Cast, $result->kind);
        self::assertSame([$input], $result->operands);
    }
    public function testBinaryPreservesLeftKeysInArrayUnion(): void
    {
        $result = (new Operations())->binary('+', Term::fromNative(['a' => 1]), Term::fromNative(['a' => 2, 'b' => 3]));
        self::assertSame(['a' => 1, 'b' => 3], $result->native());
    }
    public function testUnaryKeepsNegatedPredicatesSymbolic(): void
    {
        $input = Term::parameter('enabled', 'bool');
        $result = (new Operations())->unary('Expr_BooleanNot', $input);
        self::assertSame('!', $result->literal);
        self::assertSame([$input], $result->operands);
    }
    public function testArrayKeyKeepsLeadingZeroStrings(): void
    {
        self::assertSame('01', (new Operations())->arrayKey(Term::constant('01'))->native());
        self::assertSame(1, (new Operations())->arrayKey(Term::constant('1'))->native());
    }
    public function testNumericReturnsTypeErrorsForInvalidAggregateArithmetic(): void
    {
        $value = (new Operations())->numeric('*', Term::array([]), Term::constant(2));
        self::assertSame(TermKind::Throwable, $value->kind);
        self::assertSame('TypeError', $value->literal);
    }
    public function testCastReusesAnExistingConversionToTheSameType(): void
    {
        $semantics = new Operations();
        $cast = $semantics->cast('string', Term::parameter('id', 'int'));
        self::assertSame($cast, $semantics->cast('String', $cast));
    }
    /**
     * @param Term $value
     * @param bool|null $expected
     */
    #[DataProvider('providerTruthCategories')]
    public function testTruthDistinguishesEmptyUnknownAndIdentityValues(Term $value, ?bool $expected): void
    {
        self::assertSame($expected, (new Operations())->truth($value));
    }
    /**
     * @return array<string, array{Term, bool|null}>
     */
    public static function providerTruthCategories(): array
    {
        return ['zero' => [Term::constant(0), false], 'empty-string' => [Term::constant(''), false], 'null' => [Term::constant(null), false], 'negative' => [Term::constant(-1), true], 'numeric-looking-string' => [Term::constant('0.0'), true], 'empty-array' => [Term::array([]), false], 'nonempty-open-array' => [Term::array([Term::constant(null)], true), true], 'unknown-array' => [Term::array([], true), null], 'parameter' => [Term::parameter('flag', 'bool'), null], 'object' => [new Term(TermKind::Object, 'id'), true], 'closure' => [new Term(TermKind::Closure, 'id'), true], 'enum' => [new Term(TermKind::Enum, 'id'), true], 'uninitialized' => [new Term(TermKind::Uninitialized), false]];
    }
    /**
     * @param string $type
     * @param scalar|null $input
     * @param scalar|array<scalar|null>|null $expected
     */
    #[DataProvider('providerCastConstants')]
    public function testCastPreservesTargetScalarAndArrayConversions(string $type, int|float|string|bool|null $input, int|float|string|bool|array|null $expected): void
    {
        $result = (new Operations())->cast($type, Term::constant($input));
        self::assertSame($expected, $result->native());
    }
    /**
     * @return list<array{string, scalar|null, scalar|array<scalar|null>|null}>
     */
    public static function providerCastConstants(): array
    {
        return [['bool', '0', false], ['bool', 2, true], ['string', 42, '42'], ['string', null, ''], ['int', 3.75, 3], ['int', '12', 12], ['int', false, 0], ['float', '2.5', 2.5], ['double', 3, 3.0], ['array', null, []], ['array', 'value', ['value']]];
    }
    public function testCastRetainsUnsupportedAndConfigurationDependentBoundaries(): void
    {
        $semantics = new Operations();
        $input = Term::parameter('flag', 'bool');
        $boolean = $semantics->cast('bool', $input);
        self::assertSame(['cast', 'bool', [$input], ['type' => 'bool']], [$boolean->kind->value, $boolean->literal, $boolean->operands, $boolean->attributes]);
        self::assertSame('FLOAT_STRING_CONFIGURATION', $semantics->cast('string', Term::constant(1.5))->literal);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $semantics->cast('object', Term::constant(1))->literal);
        self::assertSame(TermKind::Opaque, $semantics->cast('string', Term::array([]))->kind);
        self::assertSame(TermKind::Opaque, $semantics->cast('string', new Term(TermKind::Object, 'id'))->kind);
    }
    public function testBinaryPreservesSymbolicRelationships(): void
    {
        $semantics = new Operations();
        $input = Term::parameter('enabled', 'bool');
        $constant = Term::constant(false);
        $predicate = $semantics->binary('xor', $input, $constant);
        self::assertSame(['binary', 'xor', 'bool'], [$predicate->kind->value, $predicate->literal, $predicate->attributes['type']]);
        self::assertSame([$input, $constant], $predicate->operands);
        self::assertFalse($semantics->binary('xor', Term::constant(true), Term::constant(true))->native());
        self::assertSame('ab', $semantics->binary('.', Term::constant('a'), Term::constant('b'))->native());
        self::assertTrue($semantics->binary('<', Term::constant(2), Term::constant(3))->native());
    }
    /**
     * @param string $operation
     * @param scalar|null $input
     * @param scalar|null $expected
     */
    #[DataProvider('providerUnaryConstants')]
    public function testUnaryUsesTheTargetOperator(string $operation, int|float|string|bool|null $input, int|float|string|bool|null $expected): void
    {
        $result = (new Operations())->unary($operation, Term::constant($input));
        self::assertSame($expected, $result->native());
    }
    /**
     * @return list<array{string, scalar|null, scalar|null}>
     */
    public static function providerUnaryConstants(): array
    {
        return [['Expr_BooleanNot', '0', true], ['Expr_BooleanNot', 1, false], ['Expr_UnaryMinus', 3, -3], ['Expr_UnaryPlus', '3', 3], ['Expr_BitwiseNot', 1, -2], ['Expr_BitwiseNot', 'A', "\xbe"]];
    }
    public function testUnaryRetainsSymbolicBitwiseInputsAndRejectsInvalidConstants(): void
    {
        $semantics = new Operations();
        $input = Term::parameter('n', 'int');
        self::assertSame('TypeError', $semantics->unary('Expr_BitwiseNot', Term::constant(null))->literal);
        $symbolic = $semantics->unary('Expr_BitwiseNot', $input);
        self::assertSame(['unary', 'Expr_BitwiseNot', [$input]], [$symbolic->kind->value, $symbolic->literal, $symbolic->operands]);
    }
    /**
     * @param scalar|null $input
     * @param int|string $expected
     */
    #[DataProvider('providerArrayKeys')]
    public function testArrayKeyUsesTargetKeyCategories(int|float|string|bool|null $input, int|string $expected): void
    {
        $result = (new Operations())->arrayKey(Term::constant($input));
        self::assertSame($expected, $result->native());
    }
    /**
     * @return list<array{scalar|null, int|string}>
     */
    public static function providerArrayKeys(): array
    {
        return [[null, ''], [false, 0], [true, 1], [3.75, 3], [-2.5, -2], [8, 8], ['0', 0], ['-3', -3], ['01', '01'], ['+1', '+1'], ['-0', '-0'], ['9223372036854775808', '9223372036854775808']];
    }
    public function testArrayKeyPreservesUnknownKeysAndRejectsAggregateKinds(): void
    {
        $semantics = new Operations();
        $input = Term::parameter('key');
        $key = $semantics->arrayKey($input);
        self::assertSame(['array-key', [$input]], [$key->kind->value, $key->operands]);
        self::assertSame('TypeError', $semantics->arrayKey(Term::array([]))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new Term(TermKind::Object, 'id'))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new Term(TermKind::Closure, 'id'))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new Term(TermKind::Enum, 'id'))->literal);
    }
    /**
     * @param string $operation
     * @param string $bound
     */
    #[DataProvider('providerNumericBounds')]
    public function testNumericRetainsOperandIdentityAndValidResultBounds(string $operation, string $bound): void
    {
        $input = Term::parameter('n', 'int');
        $constant = Term::constant(2);
        $result = (new Operations())->numeric($operation, $input, $constant);
        self::assertSame(['binary', $operation, [$input, $constant], ['type' => $bound]], [$result->kind->value, $result->literal, $result->operands, $result->attributes]);
    }
    /**
     * @return list<array{string, string}>
     */
    public static function providerNumericBounds(): array
    {
        return [['+', 'int|float|array'], ['-', 'int|float'], ['%', 'int'], ['<<', 'int'], ['>>', 'int'], ['&', 'int|string'], ['|', 'int|string'], ['^', 'int|string']];
    }
    public function testNumericRetainsPossibleArrayUnionAndRejectsKnownIncompatibleOperands(): void
    {
        $semantics = new Operations();
        $array = Term::array([]);
        $input = Term::parameter('other', 'array');
        $union = $semantics->numeric('+', $input, $array);
        self::assertSame(['binary', '+', [$input, $array], ['type' => 'array']], [$union->kind->value, $union->literal, $union->operands, $union->attributes]);
        self::assertSame('TypeError', $semantics->numeric('+', $array, Term::constant(1))->literal);
        self::assertSame('TypeError', $semantics->numeric('*', Term::constant(1), new Term(TermKind::Object, 'id'))->literal);
        self::assertSame(6, $semantics->numeric('*', Term::constant(2), Term::constant(3))->native());
    }
    #[DataProvider('providerArrayUnionRemainders')]
    public function testBinaryPreservesArrayUnionOrderAndUnknownRemainders(bool $leftOpen, bool $rightOpen): void
    {
        $left = new Term(TermKind::Array, operands: ['shared' => Term::constant(1), 'left' => Term::constant(2)], attributes: ['open' => $leftOpen]);
        $right = new Term(TermKind::Array, operands: ['shared' => Term::constant(9), 'right' => Term::constant(3)], attributes: ['open' => $rightOpen]);
        $result = (new Operations())->binary('+', $left, $right);
        self::assertSame(TermKind::Array, $result->kind);
        self::assertSame(['shared', 'left', 'right'], array_keys($result->operands));
        self::assertSame([1, 2, 3], array_column($result->operands, 'literal'));
        self::assertSame($leftOpen || $rightOpen, $result->attributes['open']);
    }
    /**
     * @return iterable<string,array{bool,bool}>
     */
    public static function providerArrayUnionRemainders(): iterable
    {
        yield 'closed' => [false, false];
        yield 'open left' => [true, false];
        yield 'open right' => [false, true];
        yield 'both open' => [true, true];
    }
    #[DataProvider('providerArrayCasts')]
    public function testCastCreatesClosedArrayShapes(int|string|null $value): void
    {
        $result = (new Operations())->cast('array', Term::constant($value));
        self::assertSame(TermKind::Array, $result->kind);
        self::assertSame($value === null ? [] : [$value], $result->native());
        self::assertFalse($result->attributes['open']);
    }
    /**
     * @return iterable<string,array{int|string|null}>
     */
    public static function providerArrayCasts(): iterable
    {
        yield 'null' => [null];
        yield 'integer' => [1];
        yield 'string' => ['value'];
    }
    public function testTruthTreatsNanAsTrueWithoutHostDiagnostics(): void
    {
        self::assertTrue((new Operations())->truth(Term::constant(NAN)));
        self::assertFalse((new Operations())->truth(Term::constant(-0.0)));
    }
    public function testCastConvertsFloatsUnderTheCapturedPrecision(): void
    {
        self::assertSame('0.33333333333333', (new Operations(14))->cast('string', Term::constant(1 / 3))->native());
        self::assertSame('0.3333333333333333', (new Operations(-1))->cast('string', Term::constant(1 / 3))->native());
        self::assertSame('v0.25', (new Operations(14))->binary('.', Term::constant('v'), Term::constant(0.25))->native());
    }
    public function testUnarySignsKeepNegativeZero(): void
    {
        self::assertSame('-0', (new Operations(14))->cast('string', (new Operations())->unary('Expr_UnaryMinus', Term::constant(0.0)))->native());
        self::assertSame('-0', (new Operations(14))->cast('string', (new Operations())->unary('Expr_UnaryPlus', Term::constant('-0.0')))->native());
    }
}
