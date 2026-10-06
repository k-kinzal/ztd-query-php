<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operations::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
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
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\FloatConversion::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
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

    public function testTruthTreatsNanAsTrueWithoutHostDiagnostics(): void
    {
        self::assertTrue((new Operations())->truth(Term::constant(NAN)));
        self::assertFalse((new Operations())->truth(Term::constant(-0.0)));
    }

    public function testCastConvertsFloatsUnderTheCapturedPrecision(): void
    {
        self::assertSame('0.33333333333333', (new Operations(14))->cast('string', Term::constant(1 / 3))->native());
        self::assertSame('0.3333333333333333', (new Operations(-1))->cast('string', Term::constant(1 / 3))->native());
        self::assertTrue((new Operations(14))->cast('string', Term::constant(0.25, true))->isSecret());
        self::assertSame('v0.25', (new Operations(14))->binary('.', Term::constant('v'), Term::constant(0.25))->native());
    }

    public function testUnarySignsKeepNegativeZero(): void
    {
        self::assertSame('-0', (new Operations(14))->cast('string', (new Operations())->unary('Expr_UnaryMinus', Term::constant(0.0)))->native());
        self::assertSame('-0', (new Operations(14))->cast('string', (new Operations())->unary('Expr_UnaryPlus', Term::constant('-0.0')))->native());
    }
}
