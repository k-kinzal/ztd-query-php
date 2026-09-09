<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\IntegerContextRule;

#[CoversClass(IntegerContextRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class IntegerContextRuleTest extends TestCase
{
    #[DataProvider('providerBoundedOptions')]
    public function testOptionsLimitsOnlyTheDeclaredNumericOption(string $context, string $option, string $expected): void
    {
        $trace = new DerivationTrace($context);
        $trace->expand(0, new Production([new Terminal($option), new Terminal('EQ'), new NonTerminal('ulong_num'), new Terminal('LONG_NUM')]), 0);
        $trace->expand(2, new Production([new Terminal('LONG_NUM')]), 0);
        self::assertSame([$option, 'EQ', $expected, 'LONG_NUM'], (new IntegerContextRule())->rewrite($trace->terminals())->names());
        self::assertSame([$option, 'EQ', $expected, 'LONG_NUM'], (new IntegerContextRule())->options($trace->terminals())->names());
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function providerBoundedOptions(): array
    {
        return [['source_def', 'SOURCE_DELAY_SYM', 'SOURCE_DELAY_NUMBER'], ['master_def', 'MASTER_DELAY_SYM', 'SOURCE_DELAY_NUMBER'], ['create_table_option', 'STATS_SAMPLE_PAGES_SYM', 'STATS_SAMPLE_PAGES_NUMBER'], ['create_table_option', 'AUTO_INC', 'LONG_NUM'], ['source_def', 'STATS_SAMPLE_PAGES_SYM', 'LONG_NUM'], ['ordinary', 'SOURCE_DELAY_SYM', 'LONG_NUM']];
    }

    #[DataProvider('providerNewNumericContexts')]
    public function testOptionsKeepsNumericLimitsWithinTheirGrammarScope(string $context, string $option, string $number, string $expected): void
    {
        $trace = new DerivationTrace($context);
        $trace->expand(0, new Production([new Terminal($option), new Terminal('EQ'), new NonTerminal($number)]), 0);
        $trace->expand(2, new Production([new Terminal('ULONGLONG_NUM')]), 0);
        $input = $trace->terminals();
        $result = (new IntegerContextRule())->rewrite($input);
        self::assertSame([$option, 'EQ', $expected], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    public static function providerNewNumericContexts(): array
    {
        return [
            ['create_table_option', 'AVG_ROW_LENGTH', 'ulonglong_num', 'AVG_ROW_LENGTH_NUMBER'],
            ['create_table_option', 'AVG_ROW_LENGTH', 'ulong_num', 'AVG_ROW_LENGTH_NUMBER'],
            ['opt_key_algo', 'ALGORITHM_SYM', 'real_ulong_num', 'KEY_ALGORITHM_NUMBER'],
            ['ordinary', 'ALGORITHM_SYM', 'real_ulong_num', 'ULONGLONG_NUM'],
            ['create_table_option', 'AUTO_INC', 'ulonglong_num', 'ULONGLONG_NUM'],
        ];
    }

    #[DataProvider('providerYearWidths')]
    public function testYearWidthConstrainsOnlyExplicitYearWidths(string $type, bool $strict): void
    {
        $trace = new DerivationTrace('type');
        $trace->expand(0, new Production([new Terminal($type), new NonTerminal('opt_field_length')]), 0);
        $trace->expand(1, new Production([new NonTerminal('field_length')]), 0);
        $trace->expand(1, new Production([new Terminal('('), new Terminal('LONG_NUM'), new Terminal(')')]), 0);
        $rule = new IntegerContextRule($strict);
        $result = $rule->yearWidth($trace->terminals());
        self::assertSame([$type, '(', $type === 'YEAR_SYM' && $strict ? 'YEAR_WIDTH_NUMBER' : 'LONG_NUM', ')'], $result->names());
        self::assertSame($result->names(), $rule->rewrite($trace->terminals())->names());
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerYearWidths(): array
    {
        return [['YEAR_SYM', true], ['CHAR_SYM', true], ['YEAR_SYM', false]];
    }

    public function testRewriteKeepsTheDefaultStatisticsOption(): void
    {
        $trace = new DerivationTrace('create_table_option');
        $trace->expand(0, new Production([new Terminal('STATS_SAMPLE_PAGES_SYM'), new Terminal('DEFAULT_SYM')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new IntegerContextRule())->rewrite($input));
    }

    #[DataProvider('providerKeyBlockSizes')]
    public function testOptionsBoundsKeyBlockSizeInOldAndModernGrammars(string $context, string $number, string $expected): void
    {
        $trace = new DerivationTrace($context);
        $trace->expand(0, new Production([new Terminal('KEY_BLOCK_SIZE'), new Terminal('EQ'), new NonTerminal($number), new Terminal('LONG_NUM')]), 0);
        $trace->expand(2, new Production([new Terminal('LONG_NUM')]), 0);
        self::assertSame(['KEY_BLOCK_SIZE', 'EQ', $expected, 'LONG_NUM'], (new IntegerContextRule())->options($trace->terminals())->names());
        self::assertSame(['KEY_BLOCK_SIZE', 'EQ', $expected, 'LONG_NUM'], (new IntegerContextRule())->rewrite($trace->terminals())->names());
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function providerKeyBlockSizes(): array
    {
        return [['create_table_option', 'ulong_num', 'KEY_BLOCK_SIZE_NUMBER'], ['create_table_option', 'ulonglong_num', 'KEY_BLOCK_SIZE_NUMBER'], ['ordinary', 'ulonglong_num', 'LONG_NUM'], ['source_def', 'ulonglong_num', 'LONG_NUM'], ['create_table_option', 'expr', 'LONG_NUM']];
    }

    public function testRewriteReplacesOnlyDiagnosticDecimalAlternatives(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('dec_num_error'), new Terminal('DECIMAL_NUM')]), 0);
        $trace->expand(0, new Production([new Terminal('DECIMAL_NUM')]), 0);
        $input = $trace->terminals();
        $result = (new IntegerContextRule())->rewrite($input);
        self::assertSame(['NUM', 'DECIMAL_NUM'], $result->names());
        self::assertSame($input->terminals[0]->id, $result->terminals[0]->id);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testRewriteConstrainsTheCheckedReplicationFlag(): void
    {
        $trace = new DerivationTrace('source_def');
        $trace->expand(0, new Production([new Terminal('SOURCE_CONNECTION_AUTO_FAILOVER_SYM'), new Terminal('EQ'), new NonTerminal('real_ulong_num')]), 0);
        $trace->expand(2, new Production([new Terminal('NUM')]), 0);
        self::assertSame(['SOURCE_CONNECTION_AUTO_FAILOVER_SYM', 'EQ', 'REPLICATION_FLAG_NUMBER'], (new IntegerContextRule())->rewrite($trace->terminals())->names());
    }

    public function testMappedKeepsCompatiblePlanOccurrenceIdentity(): void
    {
        $trace = new DerivationTrace('number');
        $trace->expand(0, new Production([new Terminal('NUM')]), 0);
        $input = $trace->terminals();
        $result = (new IntegerContextRule())->mapped($input, 0, 'FLAG', 'source');
        self::assertSame($input->terminals[0]->id, $result->terminals[0]->id);
        self::assertSame($input, (new IntegerContextRule())->mapped($input, 999, 'FLAG', 'source'));
    }
    public function testRewritePreservesDefaultAndRestrictsOnlyTheNumericTernaryOption(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('ternary_option'), new NonTerminal('ternary_option')]), 0);
        $trace->expand(0, new Production([new NonTerminal('ulong_num')]), 0);
        $trace->expand(0, new Production([new Terminal('LONG_NUM')]), 2);
        $trace->expand(1, new Production([new Terminal('DEFAULT_SYM')]), 1);
        self::assertSame(['TERNARY_OPTION_NUMBER', 'DEFAULT_SYM'], (new IntegerContextRule())->rewrite($trace->terminals())->names());
    }

    public function testRewriteConstrainsOnlyIdentifierFormSizesAndResetIndices(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('size_number'), new NonTerminal('size_number'), new NonTerminal('source_reset_options'), new Terminal('IDENT'), new Terminal('NUM')]), 0);
        $trace->expand(0, new Production([new NonTerminal('IDENT_sys')]), 1);
        $trace->expand(0, new Production([new Terminal('IDENT_QUOTED')]), 1);
        $trace->expand(1, new Production([new NonTerminal('real_ulonglong_num')]), 0);
        $trace->expand(1, new Production([new Terminal('LONG_NUM')]), 3);
        $trace->expand(2, new Production([new Terminal('TO_SYM'), new NonTerminal('real_ulonglong_num')]), 1);
        $trace->expand(3, new Production([new Terminal('HEX_NUM')]), 1);
        $input = $trace->terminals();
        $rule = new IntegerContextRule();
        $result = $rule->rewrite($input);
        self::assertSame(['SIZE_NUMBER', 'LONG_NUM', 'TO_SYM', 'BINLOG_RESET_INDEX', 'IDENT', 'NUM'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame(array_map(static fn ($terminal): int => $terminal->id, $input->terminals), array_map(static fn ($terminal): int => $terminal->id, $result->terminals));
    }

    public function testRewritePreservesEmptyResetOptions(): void
    {
        $trace = new DerivationTrace('source_reset_options');
        $trace->expand(0, new Production([]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new IntegerContextRule())->rewrite($input));
    }
}
