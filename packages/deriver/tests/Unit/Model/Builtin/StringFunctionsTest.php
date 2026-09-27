<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\StringFunctions;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringFunctions::class)]
#[UsesClass(\Deriver\Model\Builtin\TypePredicates::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
