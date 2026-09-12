<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Column\FieldLengthRule;

#[CoversClass(FieldLengthRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class FieldLengthRuleTest extends TestCase
{
    #[DataProvider('providerPrecisions')]
    public function testPrecisionRetainsBothNumericOccurrencesWithDependentDomains(string $type, string $domain): void
    {
        $terminals = [new TerminalOccurrence($type, 10, [0], ['type']), ...array_map(static fn (string $name, int $index): TerminalOccurrence => new TerminalOccurrence($name, 11 + $index, [0, 1], ['type', 'precision']), ['(', 'NUM', ',', 'NUM', ')'], [0, 1, 2, 3, 4])];
        $input = new TerminalSequence($terminals, productions: [new ProductionOccurrence(0, null, 'type', 0), new ProductionOccurrence(1, 0, 'precision', 0)]);
        $result = (new FieldLengthRule())->precision($input);
        self::assertSame([$type, '(', $domain, ',', 'NUMERIC_SCALE_NUMBER', ')'], $result->names());
        self::assertSame(array_column($input->terminals, 'id'), array_column($result->terminals, 'id'));
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, (new FieldLengthRule())->rewrite($result));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerPrecisions(): array
    {
        return [['DECIMAL_SYM', 'DECIMAL_DIGITS_NUMBER'], ['NUMERIC_SYM', 'DECIMAL_DIGITS_NUMBER'], ['FIXED_SYM', 'DECIMAL_DIGITS_NUMBER'], ['FLOAT_SYM', 'FLOAT_DIGITS_NUMBER'], ['DOUBLE_SYM', 'FLOAT_DIGITS_NUMBER']];
    }

    #[DataProvider('providerTypes')]
    public function testWidthRestrictsOnlyTheWidthOwnedByItsType(string $name, string $domain, string $context, ?string $wrapper): void
    {
        $type = new TerminalOccurrence($name, 10, [0], [$context]);
        $rules = [$context, 'field_length'];
        $open = new TerminalOccurrence('(', 11, [0, 1], $rules);
        $number = new TerminalOccurrence('ULONGLONG_NUM', 12, [0, 1], $rules);
        $close = new TerminalOccurrence(')', 13, [0, 1], $rules);
        $ordinary = new TerminalOccurrence('LONG_NUM', 14);
        $input = new TerminalSequence([$type, $open, $number, $close, $ordinary], [$type, $open, $number, $close, $ordinary], [], [new ProductionOccurrence(0, null, $context, 0), new ProductionOccurrence(1, 0, 'field_length', 1), ...($wrapper === null ? [] : [new ProductionOccurrence(2, 0, $wrapper, 0)])]);
        $rule = new FieldLengthRule();
        $result = $rule->width($input);
        self::assertSame([$name, '(', $domain, ')', 'LONG_NUM'], $result->names());
        self::assertSame($number->id, $result->terminals[2]->id);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, string, string, string|null}>
     */
    public static function providerTypes(): iterable
    {
        foreach (['INT_SYM', 'TINYINT_SYM', 'SMALLINT_SYM', 'MEDIUMINT_SYM', 'BIGINT_SYM', 'CHAR_SYM', 'NCHAR_SYM', 'NATIONAL_SYM', 'BINARY_SYM'] as $name) {
            yield [$name, 'DISPLAY_WIDTH_NUMBER', 'type', null];
        }
        foreach (['DECIMAL_SYM', 'NUMERIC_SYM', 'FIXED_SYM'] as $name) {
            yield [$name, 'DECIMAL_PRECISION_NUMBER', 'type', null];
        }
        foreach (['VARCHAR_SYM', 'NVARCHAR_SYM', 'VARBINARY_SYM'] as $name) {
            yield [$name, 'VARCHAR_LENGTH_NUMBER', 'type', null];
        }
        yield ['BIT_SYM', 'BIT_WIDTH_NUMBER', 'type', null];
        yield ['FLOAT_SYM', 'FLOAT_PRECISION_NUMBER', 'type', null];
        yield ['BLOB_SYM', 'FIELD_LENGTH_NUMBER', 'type', null];
        yield ['YEAR_SYM', 'ULONGLONG_NUM', 'type', null];
        yield ['INT_SYM', 'ULONGLONG_NUM', 'ordinary', null];
        yield ['NATIONAL_SYM', 'VARCHAR_LENGTH_NUMBER', 'type', 'nvarchar'];
        yield ['CHAR_SYM', 'VARCHAR_LENGTH_NUMBER', 'type', 'varchar'];
    }

    public function testRewritePreservesEmptyFieldLengths(): void
    {
        $input = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'field_length', 0)]);
        self::assertSame($input, (new FieldLengthRule())->rewrite($input));
    }
}
