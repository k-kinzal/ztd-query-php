<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use Deriver\Standard\StringFunctions;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringFunctions::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(StringFunctions::class)]
#[UsesClass(\Deriver\Standard\TypePredicates::class)]
#[UsesClass(Term::class)]
#[Small]
final class StringFunctionsTest extends TestCase
{
    public function testImplodeSupportsTheSingleArrayOverload(): void
    {
        self::assertSame('a2', (new StringFunctions())->implode([Term::fromNative(['a', 2])])->native());
    }
    public function testSubstringAppliesNegativeOffsetsAndLengthsInBytes(): void
    {
        self::assertSame('cd', (new StringFunctions())->substring([Term::constant('abcdef'), Term::constant(-4), Term::constant(-2)])->native());
    }
    public function testSplitPreservesNegativeLimitSemantics(): void
    {
        self::assertSame(['a', 'b'], (new StringFunctions())->split([Term::constant(','), Term::constant('a,b,c'), Term::constant(-1)])->native());
    }
    public function testSplitRejectsAnEmptySeparator(): void
    {
        self::assertSame('ValueError', (new StringFunctions())->split([Term::constant(''), Term::constant('a')])->literal);
    }
    public function testTransformChangesAsciiWithoutChangingNonAsciiBytes(): void
    {
        self::assertSame("a\xC0z", (new StringFunctions())->transform('strtolower', Term::constant("A\xC0Z"), [])->native());
    }

    /**
     * @param list<Term> $arguments Bound function arguments
     * @param mixed $expected Concrete return value
     */
    #[DataProvider('providerConcreteOperations')]
    public function testApplyEvaluatesBoundByteStringOperations(string $name, array $arguments, mixed $expected): void
    {
        self::assertSame($expected, (new StringFunctions())->apply($name, $arguments)->native());
    }

    /**
     * @return array<string, array{string, list<Term>, mixed}>
     */
    public static function providerConcreteOperations(): array
    {
        return [
            'byte length' => ['strlen', [Term::constant("é\0")], 3],
            'lower' => ['strtolower', [Term::constant('AZ-09')], 'az-09'],
            'upper' => ['strtoupper', [Term::constant('az-09')], 'AZ-09'],
            'trim mask' => ['trim', [Term::constant('abcXcba'), Term::constant('a..c')], 'X'],
            'trim empty mask' => ['trim', [Term::constant(' X '), Term::constant('')], ' X '],
            'substring nullable length' => ['substr', [Term::constant('abcdef'), Term::constant(2), Term::constant(null)], 'cdef'],
            'substring past end' => ['substr', [Term::constant('abc'), Term::constant(4)], ''],
            'split default limit' => ['explode', [Term::constant(','), Term::constant('a,,b,')], ['a', '', 'b', '']],
            'split zero limit' => ['explode', [Term::constant(','), Term::constant('a,b'), Term::constant(0)], ['a,b']],
            'split positive limit' => ['explode', [Term::constant(','), Term::constant('a,b,c'), Term::constant(2)], ['a', 'b,c']],
            'join alias' => ['join', [Term::constant('|'), Term::fromNative(['first' => 'a', 8 => 2, false, null])], 'a|2||'],
            'join explicit null' => ['implode', [Term::fromNative(['a', 2]), Term::constant(null)], 'a2'],
            'join empty array' => ['implode', [Term::constant('|'), Term::array([])], ''],
        ];
    }

    /**
     * @param list<Term> $arguments Bound function arguments
     */
    #[DataProvider('providerUnsupportedOperations')]
    public function testApplyKeepsUnresolvedEffectsAndExceptionsExplicit(string $name, array $arguments, string $type): void
    {
        $result = (new StringFunctions())->apply($name, $arguments);
        self::assertSame('opaque', $result->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $result->literal);
        self::assertSame($type, $result->attributes['type']);
    }

    /**
     * @return array<string, array{string, list<Term>, string}>
     */
    public static function providerUnsupportedOperations(): array
    {
        return [
            'unknown split separator can be empty' => ['explode', [Term::parameter('separator', 'string'), Term::constant('a,b')], 'array'],
            'unknown split string' => ['explode', [Term::constant(','), Term::parameter('string', 'string')], 'array'],
            'unknown split limit' => ['explode', [Term::constant(','), Term::constant('a,b'), Term::parameter('limit', 'int')], 'array'],
            'unknown substring content' => ['substr', [Term::parameter('string', 'string'), Term::constant(0)], 'string'],
            'unknown substring offset' => ['substr', [Term::constant('abc'), Term::parameter('offset', 'int')], 'string'],
            'unknown substring length' => ['substr', [Term::constant('abc'), Term::constant(0), Term::parameter('length', 'int')], 'string'],
            'unknown trim range can warn' => ['trim', [Term::parameter('string', 'string'), Term::parameter('characters', 'string')], 'string'],
            'known string unknown trim range' => ['trim', [Term::constant('abc'), Term::parameter('characters', 'string')], 'string'],
            'unknown join elements may invoke methods' => ['implode', [Term::constant(','), Term::parameter('array', 'array')], 'string'],
            'open join array' => ['implode', [Term::constant(','), Term::array([Term::constant('a')], true)], 'string'],
            'nullable join array' => ['implode', [Term::array([]), Term::parameter('array', 'array|null')], 'string'],
            'unknown join overload' => ['implode', [Term::parameter('separator', 'array|string'), Term::array([])], 'string'],
            'object join element' => ['implode', [Term::constant(','), Term::array([new Term('object', 'identity')])], 'string'],
            'nested join element' => ['implode', [Term::constant(','), Term::array([Term::array([])])], 'string'],
            'unknown operation' => ['other', [Term::constant('abc')], 'mixed'],
        ];
    }

    public function testImplodeRejectsTheRemovedReversedArgumentOverload(): void
    {
        $result = (new StringFunctions())->implode([Term::fromNative(['a']), Term::fromNative(['b'])]);
        self::assertSame('throwable', $result->kind);
        self::assertSame('TypeError', $result->literal);
    }

    public function testImplodeRejectsASingleStringArgument(): void
    {
        $result = (new StringFunctions())->implode([Term::constant(',')]);
        self::assertSame('throwable', $result->kind);
        self::assertSame('TypeError', $result->literal);
    }

    public function testImplodePreservesUnknownScalarOperandsAndSeparator(): void
    {
        $separator = Term::parameter('separator', 'string');
        $part = Term::parameter('part', 'string');
        $result = (new StringFunctions())->implode([$separator, Term::array([Term::constant('a'), $part])]);
        self::assertSame('concat', $result->kind);
        self::assertSame('string', $result->attributes['type']);
        self::assertSame($part, $result->operands[1]->operands[0]);
        self::assertSame($separator, $result->operands[0]->operands[0]->operands[1]->operands[0]);
    }

    /**
     * @param list<Term> $arguments Bound function arguments
     */
    #[DataProvider('providerSecretOperations')]
    public function testApplyKeepsAllEvaluatedArgumentSecrets(string $name, array $arguments): void
    {
        self::assertTrue((new StringFunctions())->apply($name, $arguments)->isSecret());
    }

    /**
     * @return array<string, array{string, list<Term>}>
     */
    public static function providerSecretOperations(): array
    {
        return [
            'length' => ['strlen', [Term::constant('a', true)]],
            'lower' => ['strtolower', [Term::constant('A', true)]],
            'upper' => ['strtoupper', [Term::constant('a', true)]],
            'trim mask' => ['trim', [Term::constant('abc'),Term::constant('a', true)]],
            'substring string' => ['substr', [Term::constant('abc', true),Term::constant(0)]],
            'substring offset' => ['substr', [Term::constant('abc'),Term::constant(0, true)]],
            'substring length' => ['substr', [Term::constant('abc'),Term::constant(0),Term::constant(1, true)]],
            'split separator' => ['explode', [Term::constant(',', true),Term::constant('a,b')]],
            'split string' => ['explode', [Term::constant(','),Term::constant('a,b', true)]],
            'split limit' => ['explode', [Term::constant(','),Term::constant('a,b'),Term::constant(2, true)]],
            'join separator' => ['implode', [Term::constant(',', true),Term::fromNative(['a','b'])]],
            'join element' => ['implode', [Term::constant(','),Term::fromNative(['a'], true)]],
        ];
    }

    public function testApplyRetainsSafeSymbolicLengthAndItsType(): void
    {
        $string = Term::parameter('string', 'string');
        $result = (new StringFunctions())->apply('strlen', [$string]);
        self::assertSame('intrinsic', $result->kind);
        self::assertSame('strlen', $result->literal);
        self::assertSame([$string], $result->operands);
        self::assertSame('int', $result->attributes['type']);
    }

    public function testTransformRejectsNonStringPayloads(): void
    {
        self::assertSame('opaque', (new StringFunctions())->transform('trim', Term::constant(4), [])->kind);
    }

    #[DataProvider('providerMasks')]
    public function testValidMaskChecksByteRangesBeforeHostEvaluation(string $mask, bool $expected): void
    {
        self::assertSame($expected, (new StringFunctions())->validMask($mask));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providerMasks(): array
    {
        return [
            'empty' => ['', true], 'literal dot' => ['.', true], 'literal characters' => ['abz', true],
            'ordered' => ['a..z', true], 'equal endpoints' => ['a..a', true], 'multiple' => ['a..cz..z', true],
            'binary bytes' => ["\0..\xff", true], 'dot endpoint' => ['...z', true], 'dots only' => ['....', true],
            'missing left' => ['..a', false], 'missing right' => ['a..', false], 'descending' => ['z..a', false],
            'overlap' => ['a..b..c', false], 'two dots' => ['..', false], 'three dots' => ['...', false],
        ];
    }

    public function testApplyKeepsMalformedTrimRangesAtAnExplicitBoundary(): void
    {
        $result = (new StringFunctions())->apply('trim', [Term::constant('abc'), Term::constant('z..a')]);
        self::assertSame('opaque', $result->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $result->literal);
    }
}
