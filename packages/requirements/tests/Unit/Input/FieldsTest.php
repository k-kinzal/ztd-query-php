<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;

#[CoversClass(Fields::class)]
#[Small]
final class FieldsTest extends TestCase
{
    public function testMappingReturnsTheMapping(): void
    {
        self::assertSame(['a' => 1, 'b' => [2]], Fields::mapping(['a' => 1, 'b' => [2]], 'source'));
    }

    public function testMappingAcceptsAnEmptyArray(): void
    {
        self::assertSame([], Fields::mapping([], 'metadata'));
    }

    #[DataProvider('providerMappingRejectsNonArrays')]
    public function testMappingRejectsNonArrays(mixed $value): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('source must be a mapping.');
        Fields::mapping($value, 'source');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function providerMappingRejectsNonArrays(): array
    {
        return [
            'null' => [null],
            'string' => ['text'],
            'integer' => [1],
            'object' => [new stdClass()],
        ];
    }

    #[DataProvider('providerMappingRejectsIntegerKeys')]
    public function testMappingRejectsIntegerKeys(mixed $value): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('item must have string keys.');
        Fields::mapping($value, 'item');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function providerMappingRejectsIntegerKeys(): array
    {
        return [
            'list' => [['a', 'b']],
            'integer key after string keys' => [['a' => 1, 'b' => 2, 5 => 3]],
        ];
    }

    public function testKeysAcceptsAllowedKeys(): void
    {
        Fields::keys(['selector' => 'p', 'quote' => 'q'], ['selector', 'quote', 'other'], 'evidence');
        $this->addToAssertionCount(1);
    }

    public function testKeysAcceptsAnEmptyMapping(): void
    {
        Fields::keys([], [], 'evidence');
        $this->addToAssertionCount(1);
    }

    public function testKeysRejectsAnUnknownKeyAfterKnownOnes(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("evidence: unknown field 'extra'.");
        Fields::keys(['selector' => 'p', 'extra' => 'x'], ['selector', 'quote'], 'evidence');
    }

    public function testTextReturnsTheValue(): void
    {
        self::assertSame(' spaced ', Fields::text(['id' => ' spaced '], 'id'));
    }

    public function testTextPrefersTheValueToTheDefault(): void
    {
        self::assertSame('requirement', Fields::text(['kind' => 'requirement'], 'kind', 'specification'));
    }

    public function testTextUsesTheDefaultWhenAbsent(): void
    {
        self::assertSame('specification', Fields::text([], 'kind', 'specification'));
    }

    public function testTextUsesTheDefaultWhenNull(): void
    {
        self::assertSame('.', Fields::text(['cwd' => null], 'cwd', '.'));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('providerTextRejectsMissingOrBlankValues')]
    public function testTextRejectsMissingOrBlankValues(array $data, ?string $default): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('id must be a nonempty string.');
        Fields::text($data, 'id', $default);
    }

    /**
     * @return array<string, array{array<string, mixed>, ?string}>
     */
    public static function providerTextRejectsMissingOrBlankValues(): array
    {
        return [
            'missing' => [[], null],
            'empty' => [['id' => ''], null],
            'blank' => [['id' => " \t\n"], null],
            'integer' => [['id' => 1], null],
            'list' => [['id' => ['a']], null],
            'blank default' => [[], ' '],
            'blank value over default' => [['id' => ' '], 'default'],
        ];
    }

    public function testSequenceReturnsTheList(): void
    {
        self::assertSame([1, 'a', null], Fields::sequence([1, 'a', null], 'tests'));
    }

    public function testSequenceAcceptsAnEmptyList(): void
    {
        self::assertSame([], Fields::sequence([], 'tests'));
    }

    #[DataProvider('providerSequenceRejectsNonLists')]
    public function testSequenceRejectsNonLists(mixed $value): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('tests must be a list.');
        Fields::sequence($value, 'tests');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function providerSequenceRejectsNonLists(): array
    {
        return [
            'string' => ['a'],
            'null' => [null],
            'mapping' => [['a' => 1]],
            'sparse' => [[1 => 'a']],
        ];
    }

    public function testStringsReturnsTheStrings(): void
    {
        self::assertSame(['a', ' b '], Fields::strings(['a', ' b '], 'labels'));
    }

    public function testStringsKeepsDuplicatesWhenNotUnique(): void
    {
        self::assertSame(['php', 'php'], Fields::strings(['php', 'php'], 'runner.command', false));
    }

    public function testStringsRejectsDuplicatesByDefault(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('labels contains duplicates.');
        Fields::strings(['a', 'b', 'a'], 'labels');
    }

    public function testStringsRejectsANonList(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('labels must be a list.');
        Fields::strings('a', 'labels');
    }

    #[DataProvider('providerStringsRejectsNonStringEntries')]
    public function testStringsRejectsNonStringEntries(mixed $value): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('labels must contain nonempty strings.');
        Fields::strings($value, 'labels', false);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function providerStringsRejectsNonStringEntries(): array
    {
        return [
            'integer' => [['a', 1]],
            'empty' => [['a', '']],
            'blank' => [['a', '  ']],
            'null' => [[null]],
        ];
    }

    #[DataProvider('providerPercentageAcceptsNumbersInRange')]
    public function testPercentageAcceptsNumbersInRange(mixed $value, float $expected): void
    {
        self::assertSame($expected, Fields::percentage($value, 'coverage.minimum'));
    }

    /**
     * @return array<string, array{mixed, float}>
     */
    public static function providerPercentageAcceptsNumbersInRange(): array
    {
        return [
            'zero' => [0, 0.0],
            'integer' => [50, 50.0],
            'float' => [12.5, 12.5],
            'hundred' => [100, 100.0],
            'hundred float' => [100.0, 100.0],
        ];
    }

    #[DataProvider('providerPercentageRejectsOtherValues')]
    public function testPercentageRejectsOtherValues(mixed $value): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('coverage.minimum must be a number from 0 to 100.');
        Fields::percentage($value, 'coverage.minimum');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function providerPercentageRejectsOtherValues(): array
    {
        return [
            'numeric string' => ['50'],
            'null' => [null],
            'boolean' => [true],
            'negative' => [-1],
            'slightly negative' => [-0.001],
            'above' => [101],
            'slightly above' => [100.001],
            'infinite' => [INF],
            'not a number' => [NAN],
        ];
    }
}
