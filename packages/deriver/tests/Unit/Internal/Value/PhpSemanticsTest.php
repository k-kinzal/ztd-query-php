<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Value\PhpSemantics::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PhpSemanticsTest extends TestCase
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
        self::assertNull((new \Deriver\Internal\Value\PhpSemantics())->truth(\Deriver\Value\Term::array([], true)));
        self::assertFalse((new \Deriver\Internal\Value\PhpSemantics())->truth(\Deriver\Value\Term::constant('0')));
    }

    public function testCastPreservesSymbolicInputs(): void
    {
        $input = \Deriver\Value\Term::parameter('id', 'int');
        $result = (new \Deriver\Internal\Value\PhpSemantics())->cast('string', $input);
        self::assertSame('cast', $result->kind);
        self::assertSame([$input], $result->operands);
    }

    public function testBinaryPreservesLeftKeysInArrayUnion(): void
    {
        $result = (new \Deriver\Internal\Value\PhpSemantics())->binary('+', \Deriver\Value\Term::fromNative(['a' => 1]), \Deriver\Value\Term::fromNative(['a' => 2, 'b' => 3]));
        self::assertSame(['a' => 1, 'b' => 3], $result->native());
    }

    public function testUnaryKeepsNegatedPredicatesSymbolic(): void
    {
        $input = \Deriver\Value\Term::parameter('enabled', 'bool');
        $result = (new \Deriver\Internal\Value\PhpSemantics())->unary('Expr_BooleanNot', $input);
        self::assertSame('!', $result->literal);
        self::assertSame([$input], $result->operands);
    }

    public function testArrayKeyKeepsLeadingZeroStrings(): void
    {
        self::assertSame('01', (new \Deriver\Internal\Value\PhpSemantics())->arrayKey(\Deriver\Value\Term::constant('01'))->native());
        self::assertSame(1, (new \Deriver\Internal\Value\PhpSemantics())->arrayKey(\Deriver\Value\Term::constant('1'))->native());
    }

    public function testNumericReturnsTypeErrorsForInvalidAggregateArithmetic(): void
    {
        $value = (new \Deriver\Internal\Value\PhpSemantics())->numeric('*', \Deriver\Value\Term::array([]), \Deriver\Value\Term::constant(2));
        self::assertSame('throwable', $value->kind);
        self::assertSame('TypeError', $value->literal);
    }
    public function testCastReusesAnExistingConversionToTheSameType(): void
    {
        $semantics = new \Deriver\Internal\Value\PhpSemantics();
        $cast = $semantics->cast('string', \Deriver\Value\Term::parameter('id', 'int'));
        self::assertSame($cast, $semantics->cast('String', $cast));
    }

    /**
     * @param \Deriver\Value\Term $value
     * @param bool|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTruthCategories')]
    public function testTruthDistinguishesEmptyUnknownAndIdentityValues(\Deriver\Value\Term $value, ?bool $expected): void
    {
        self::assertSame($expected, (new \Deriver\Internal\Value\PhpSemantics())->truth($value));
    }

    /**
     * @return array<string, array{\Deriver\Value\Term, bool|null}>
     */
    public static function providerTruthCategories(): array
    {
        return [
            'zero' => [\Deriver\Value\Term::constant(0), false],
            'empty-string' => [\Deriver\Value\Term::constant(''), false],
            'null' => [\Deriver\Value\Term::constant(null), false],
            'negative' => [\Deriver\Value\Term::constant(-1), true],
            'numeric-looking-string' => [\Deriver\Value\Term::constant('0.0'), true],
            'empty-array' => [\Deriver\Value\Term::array([]), false],
            'nonempty-open-array' => [\Deriver\Value\Term::array([\Deriver\Value\Term::constant(null)], true), true],
            'unknown-array' => [\Deriver\Value\Term::array([], true), null],
            'parameter' => [\Deriver\Value\Term::parameter('flag', 'bool'), null],
            'object' => [new \Deriver\Value\Term('object', 'id'), true],
            'closure' => [new \Deriver\Value\Term('closure', 'id'), true],
            'enum' => [new \Deriver\Value\Term('enum', 'id'), true],
            'uninitialized' => [new \Deriver\Value\Term('uninitialized'), false],
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
        $result = (new \Deriver\Internal\Value\PhpSemantics())->cast($type, \Deriver\Value\Term::constant($input, true));
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
        $semantics = new \Deriver\Internal\Value\PhpSemantics();
        $input = \Deriver\Value\Term::parameter('flag', 'bool');
        $boolean = $semantics->cast('bool', $input);
        self::assertSame(['cast', 'bool', [$input], ['type' => 'bool']], [$boolean->kind, $boolean->literal, $boolean->operands, $boolean->attributes]);
        self::assertSame('FLOAT_STRING_CONFIGURATION', $semantics->cast('string', \Deriver\Value\Term::constant(1.5))->literal);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $semantics->cast('object', \Deriver\Value\Term::constant(1))->literal);
        self::assertSame('opaque', $semantics->cast('string', \Deriver\Value\Term::array([]))->kind);
        self::assertSame('opaque', $semantics->cast('string', new \Deriver\Value\Term('object', 'id'))->kind);
        self::assertTrue($semantics->cast('bool', \Deriver\Value\Term::constant(1, true))->isSecret());
        self::assertTrue($semantics->cast('int', \Deriver\Value\Term::constant(2.5, true))->isSecret());
    }

    public function testBinaryPreservesSymbolicRelationshipsAndSecretResults(): void
    {
        $semantics = new \Deriver\Internal\Value\PhpSemantics();
        $input = \Deriver\Value\Term::parameter('enabled', 'bool');
        $constant = \Deriver\Value\Term::constant(false);
        $predicate = $semantics->binary('xor', $input, $constant);
        self::assertSame(['binary', 'xor', 'bool'], [$predicate->kind, $predicate->literal, $predicate->attributes['type']]);
        self::assertSame([$input, $constant], $predicate->operands);
        self::assertFalse($semantics->binary('xor', \Deriver\Value\Term::constant(true), \Deriver\Value\Term::constant(true))->native());
        self::assertTrue($semantics->binary('xor', \Deriver\Value\Term::constant(true, true), \Deriver\Value\Term::constant(false))->isSecret());
        self::assertSame('ab', $semantics->binary('.', \Deriver\Value\Term::constant('a'), \Deriver\Value\Term::constant('b'))->native());
        self::assertTrue($semantics->binary('.', \Deriver\Value\Term::constant('a'), \Deriver\Value\Term::constant('b', true))->isSecret());
        self::assertTrue($semantics->binary('<', \Deriver\Value\Term::constant(2), \Deriver\Value\Term::constant(3))->native());
    }

    /**
     * @param string $operation
     * @param scalar|null $input
     * @param scalar|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnaryConstants')]
    public function testUnaryUsesTheTargetOperatorAndPreservesSecrecy(string $operation, int|float|string|bool|null $input, int|float|string|bool|null $expected): void
    {
        $result = (new \Deriver\Internal\Value\PhpSemantics())->unary($operation, \Deriver\Value\Term::constant($input, true));
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
        $semantics = new \Deriver\Internal\Value\PhpSemantics();
        $input = \Deriver\Value\Term::parameter('n', 'int');
        self::assertSame('TypeError', $semantics->unary('Expr_BitwiseNot', \Deriver\Value\Term::constant(null))->literal);
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
        $result = (new \Deriver\Internal\Value\PhpSemantics())->arrayKey(\Deriver\Value\Term::constant($input, true));
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
        $semantics = new \Deriver\Internal\Value\PhpSemantics();
        $input = \Deriver\Value\Term::parameter('key');
        $key = $semantics->arrayKey($input);
        self::assertSame(['array-key', [$input]], [$key->kind, $key->operands]);
        self::assertSame('TypeError', $semantics->arrayKey(\Deriver\Value\Term::array([]))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new \Deriver\Value\Term('object', 'id'))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new \Deriver\Value\Term('closure', 'id'))->literal);
        self::assertSame('TypeError', $semantics->arrayKey(new \Deriver\Value\Term('enum', 'id'))->literal);
    }

    /**
     * @param string $operation
     * @param string $bound
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNumericBounds')]
    public function testNumericRetainsOperandIdentityAndValidResultBounds(string $operation, string $bound): void
    {
        $input = \Deriver\Value\Term::parameter('n', 'int');
        $constant = \Deriver\Value\Term::constant(2);
        $result = (new \Deriver\Internal\Value\PhpSemantics())->numeric($operation, $input, $constant);
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
        $semantics = new \Deriver\Internal\Value\PhpSemantics();
        $array = \Deriver\Value\Term::array([]);
        $input = \Deriver\Value\Term::parameter('other', 'array');
        $union = $semantics->numeric('+', $input, $array);
        self::assertSame(['binary', '+', [$input, $array], ['type' => 'array']], [$union->kind, $union->literal, $union->operands, $union->attributes]);
        self::assertSame('TypeError', $semantics->numeric('+', $array, \Deriver\Value\Term::constant(1))->literal);
        self::assertSame('TypeError', $semantics->numeric('*', \Deriver\Value\Term::constant(1), new \Deriver\Value\Term('object', 'id'))->literal);
        self::assertSame(6, $semantics->numeric('*', \Deriver\Value\Term::constant(2), \Deriver\Value\Term::constant(3))->native());
    }
}
