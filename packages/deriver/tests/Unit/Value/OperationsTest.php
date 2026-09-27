<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
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
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
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
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\CallableCompiler;
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
use Deriver\Value\Arrays;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\IntegerConversion;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operations::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
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
#[UsesClass(AggregateLowering::class)]
#[UsesClass(CallableCompiler::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(IntegerConversion::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Term::class)]
#[Small]
final class OperationsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testTruthPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){return [2=>"left","x"=>1]+[2=>"right",3=>"new"];}');
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
        self::assertSame('cast', $result->kind);
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
        self::assertSame('throwable', $value->kind);
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTruthCategories')]
    public function testTruthDistinguishesEmptyUnknownAndIdentityValues(Term $value, ?bool $expected): void
    {
        self::assertSame($expected, (new Operations())->truth($value));
    }

    /**
     * @return array<string, array{Term, bool|null}>
     */
    public static function providerTruthCategories(): array
    {
        return [
            'zero' => [Term::constant(0), false],
            'empty-string' => [Term::constant(''), false],
            'null' => [Term::constant(null), false],
            'negative' => [Term::constant(-1), true],
            'numeric-looking-string' => [Term::constant('0.0'), true],
            'empty-array' => [Term::array([]), false],
            'nonempty-open-array' => [Term::array([Term::constant(null)], true), true],
            'unknown-array' => [Term::array([], true), null],
            'parameter' => [Term::parameter('flag', 'bool'), null],
            'object' => [new Term('object', 'id'), true],
            'closure' => [new Term('closure', 'id'), true],
            'enum' => [new Term('enum', 'id'), true],
            'uninitialized' => [new Term('uninitialized'), false],
        ];
    }

    /**
     * @param string $type
     * @param scalar|null $input
     * @param scalar|array<scalar|null>|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCastConstants')]
    public function testCastPreservesTargetScalarAndArrayConversions(string $type, int|float|string|bool|null $input, int|float|string|bool|array|null $expected): void
    {
        $result = (new Operations())->cast($type, Term::constant($input, true));
        self::assertSame($expected, $result->native());
    }

    /**
     * @return list<array{string, scalar|null, scalar|array<scalar|null>|null}>
     */
    public static function providerCastConstants(): array
    {
        return [
            ['bool', '0', false], ['bool', 2, true], ['string', 42, '42'], ['string', null, ''],
            ['int', 3.75, 3], ['int', '12', 12], ['int', false, 0], ['float', '2.5', 2.5],
            ['double', 3, 3.0], ['array', null, []], ['array', 'value', ['value']],
        ];
    }

    public function testCastRetainsUnsupportedAndConfigurationDependentBoundaries(): void
    {
        $semantics = new Operations();
        $input = Term::parameter('flag', 'bool');
        $boolean = $semantics->cast('bool', $input);
        self::assertSame(['cast', 'bool', [$input], ['type' => 'bool']], [$boolean->kind, $boolean->literal, $boolean->operands, $boolean->attributes]);
        self::assertSame('FLOAT_STRING_CONFIGURATION', $semantics->cast('string', Term::constant(1.5))->literal);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $semantics->cast('object', Term::constant(1))->literal);
        self::assertSame('opaque', $semantics->cast('string', Term::array([]))->kind);
        self::assertSame('opaque', $semantics->cast('string', new Term('object', 'id'))->kind);
        self::assertTrue($semantics->cast('bool', Term::constant(1, true))->isSecret());
        self::assertTrue($semantics->cast('int', Term::constant(2.5, true))->isSecret());
    }

    public function testBinaryPreservesSymbolicRelationshipsAndSecretResults(): void
    {
        $semantics = new Operations();
        $input = Term::parameter('enabled', 'bool');
        $constant = Term::constant(false);
        $predicate = $semantics->binary('xor', $input, $constant);
        self::assertSame(['binary', 'xor', 'bool'], [$predicate->kind, $predicate->literal, $predicate->attributes['type']]);
        self::assertSame([$input, $constant], $predicate->operands);
        self::assertFalse($semantics->binary('xor', Term::constant(true), Term::constant(true))->native());
        self::assertTrue($semantics->binary('xor', Term::constant(true, true), Term::constant(false))->isSecret());
        self::assertSame('ab', $semantics->binary('.', Term::constant('a'), Term::constant('b'))->native());
        self::assertTrue($semantics->binary('.', Term::constant('a'), Term::constant('b', true))->isSecret());
        self::assertTrue($semantics->binary('<', Term::constant(2), Term::constant(3))->native());
    }

    /**
     * @param string $operation
     * @param scalar|null $input
     * @param scalar|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnaryConstants')]
    public function testUnaryUsesTheTargetOperatorAndPreservesSecrecy(string $operation, int|float|string|bool|null $input, int|float|string|bool|null $expected): void
    {
        $result = (new Operations())->unary($operation, Term::constant($input, true));
        self::assertSame($expected, $result->native());
        self::assertTrue($result->isSecret());
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
        self::assertSame(['unary', 'Expr_BitwiseNot', [$input]], [$symbolic->kind, $symbolic->literal, $symbolic->operands]);
    }

    /**
     * @param scalar|null $input
     * @param int|string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerArrayKeys')]
    public function testArrayKeyUsesTargetKeyCategories(int|float|string|bool|null $input, int|string $expected): void
    {
        $result = (new Operations())->arrayKey(Term::constant($input, true));
        self::assertSame($expected, $result->native());
        self::assertTrue($result->isSecret());
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
        self::assertSame(['array-key', [$input]], [$key->kind, $key->operands]);
        self::assertSame('TypeError', $semantics->arrayKey(Term::array([]))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new Term('object', 'id'))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new Term('closure', 'id'))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new Term('enum', 'id'))->literal);
    }

    /**
     * @param string $operation
     * @param string $bound
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNumericBounds')]
    public function testNumericRetainsOperandIdentityAndValidResultBounds(string $operation, string $bound): void
    {
        $input = Term::parameter('n', 'int');
        $constant = Term::constant(2);
        $result = (new Operations())->numeric($operation, $input, $constant);
        self::assertSame(['binary', $operation, [$input, $constant], ['type' => $bound]], [$result->kind, $result->literal, $result->operands, $result->attributes]);
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
        self::assertSame(['binary', '+', [$input, $array], ['type' => 'array']], [$union->kind, $union->literal, $union->operands, $union->attributes]);
        self::assertSame('TypeError', $semantics->numeric('+', $array, Term::constant(1))->literal);
        self::assertSame('TypeError', $semantics->numeric('*', Term::constant(1), new Term('object', 'id'))->literal);
        self::assertSame(6, $semantics->numeric('*', Term::constant(2), Term::constant(3))->native());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerArrayUnionLabels')]
    public function testBinaryPreservesAggregateConfidentialityAndUnknownArrayRemainders(bool $leftSecret, bool $rightSecret, bool $leftOpen, bool $rightOpen): void
    {
        $left = new Term('array', operands:['shared' => Term::constant(1), 'left' => Term::constant(2)], attributes:['open' => $leftOpen], secret:$leftSecret);
        $right = new Term('array', operands:['shared' => Term::constant(9), 'right' => Term::constant(3)], attributes:['open' => $rightOpen], secret:$rightSecret);
        $result = (new Operations())->binary('+', $left, $right);
        self::assertSame('array', $result->kind);
        self::assertSame(['shared', 'left', 'right'], array_keys($result->operands));
        self::assertSame([1, 2, 3], array_column($result->operands, 'literal'));
        self::assertSame($leftSecret || $rightSecret, $result->isSecret());
        self::assertSame($leftOpen || $rightOpen, $result->attributes['open']);
    }

    /**
     * @return iterable<string,array{bool,bool,bool,bool}>
     */
    public static function providerArrayUnionLabels(): iterable
    {
        foreach ([false, true] as $leftSecret) {
            foreach ([false, true] as $rightSecret) {
                foreach ([false, true] as $leftOpen) {
                    foreach ([false, true] as $rightOpen) {
                        yield (int)$leftSecret . ':' . (int)$rightSecret . ':' . (int)$leftOpen . ':' . (int)$rightOpen => [$leftSecret, $rightSecret, $leftOpen, $rightOpen];
                    }
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerArrayCastLabels')]
    public function testCastRetainsConfidentialityEvenWhenNoArrayEntriesRemain(int|string|null $value, bool $secret): void
    {
        $result = (new Operations())->cast('array', Term::constant($value, $secret));
        self::assertSame('array', $result->kind);
        self::assertSame($value === null ? [] : [$value], $result->native());
        self::assertSame($secret, $result->isSecret());
        self::assertFalse($result->attributes['open']);
    }

    /**
     * @return iterable<string,array{int|string|null,bool}>
     */
    public static function providerArrayCastLabels(): iterable
    {
        foreach ([null, 1, 'value'] as $index => $value) {
            yield $index . ':public' => [$value, false];
            yield $index . ':confidential' => [$value, true];
        }
    }
}
