<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\JsonOptionsRule;

#[CoversClass(JsonOptionsRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class JsonOptionsRuleTest extends TestCase
{
    public function testBehaviorsPreservesEmptyAndUnrelatedChildProductions(): void
    {
        $terminal = new TerminalOccurrence('TRUE_P', 10, [0, 1, 3], ['func_expr_common_subexpr', 'json_behavior_clause_opt', 'other']);
        $input = new TerminalSequence([$terminal], [$terminal], [], [
            new ProductionOccurrence(0, null, 'func_expr_common_subexpr', 0),
            new ProductionOccurrence(1, 0, 'json_behavior_clause_opt', 0),
            new ProductionOccurrence(2, 1, 'json_behavior', 0),
            new ProductionOccurrence(3, 1, 'other', 0),
        ]);
        self::assertSame($input, (new JsonOptionsRule())->behaviors($input, 0, ['ERROR_P']));
    }

    #[DataProvider('providerBehaviors')]
    public function testRewritePreservesAllowedBehaviorsAndRepairsForbiddenAlternatives(string $kind, string $behavior, string $expected): void
    {
        $owner = $kind === 'column' ? 'json_table_column_definition' : 'func_expr_common_subexpr';
        $clause = in_array($kind, ['JSON_EXISTS', 'column'], true) ? 'json_on_error_clause_opt' : 'json_behavior_clause_opt';
        $terminals = [
            new TerminalOccurrence($kind, 10, [0], [$owner]),
            new TerminalOccurrence($behavior, 11, [0, 1, 2], [$owner, $clause, 'json_behavior']),
            new TerminalOccurrence('ON', 12, [0, 1], [$owner, $clause]),
            new TerminalOccurrence('ERROR_P', 13, [0, 1], [$owner, $clause]),
        ];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, $owner, 0),
            new ProductionOccurrence(1, 0, $clause, 0),
            new ProductionOccurrence(2, 1, 'json_behavior', 0),
        ]);
        $rule = new JsonOptionsRule();
        $result = $rule->rewrite($input);
        self::assertSame([$kind, $expected, 'ON', 'ERROR_P'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[2], $result->terminals[2]);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerBehaviors(): iterable
    {
        yield 'exists default' => ['JSON_EXISTS', 'DEFAULT', 'ERROR_P'];
        yield 'column default' => ['column', 'DEFAULT', 'ERROR_P'];
        yield 'exists null' => ['JSON_EXISTS', 'NULL_P', 'ERROR_P'];
        yield 'exists empty' => ['JSON_EXISTS', 'EMPTY_P', 'ERROR_P'];
        yield 'exists true' => ['JSON_EXISTS', 'TRUE_P', 'TRUE_P'];
        yield 'exists false' => ['JSON_EXISTS', 'FALSE_P', 'FALSE_P'];
        yield 'exists unknown' => ['JSON_EXISTS', 'UNKNOWN', 'UNKNOWN'];
        yield 'value empty' => ['JSON_VALUE', 'EMPTY_P', 'ERROR_P'];
        yield 'value default' => ['JSON_VALUE', 'DEFAULT', 'DEFAULT'];
        yield 'value null' => ['JSON_VALUE', 'NULL_P', 'NULL_P'];
        yield 'query true' => ['JSON_QUERY', 'TRUE_P', 'ERROR_P'];
        yield 'query empty' => ['JSON_QUERY', 'EMPTY_P', 'EMPTY_P'];
        yield 'query default' => ['JSON_QUERY', 'DEFAULT', 'DEFAULT'];
        yield 'unrelated function' => ['JSON_OBJECT', 'TRUE_P', 'TRUE_P'];
    }

    #[DataProvider('providerQuotes')]
    public function testRewriteScopesQuotesToTheDirectWrapper(string $wrapper, string $quotes, bool $removed): void
    {
        $terminals = [
            new TerminalOccurrence('JSON_QUERY', 10, [0], ['func_expr_common_subexpr']),
            new TerminalOccurrence($wrapper, 11, [0, 1], ['func_expr_common_subexpr', 'json_wrapper_behavior']),
            new TerminalOccurrence($quotes, 12, [0, 2], ['func_expr_common_subexpr', 'json_quotes_clause_opt']),
            new TerminalOccurrence('OMIT', 13, [0, 3, 4], ['func_expr_common_subexpr', 'nested', 'json_quotes_clause_opt']),
        ];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, 'func_expr_common_subexpr', 0),
            new ProductionOccurrence(1, 0, 'json_wrapper_behavior', 0),
            new ProductionOccurrence(2, 0, 'json_quotes_clause_opt', 0),
            new ProductionOccurrence(3, 0, 'nested', 0),
            new ProductionOccurrence(4, 3, 'json_quotes_clause_opt', 0),
        ]);
        $rule = new JsonOptionsRule();
        $result = $rule->rewrite($input);
        self::assertSame($removed ? [$terminals[0], $terminals[1], $terminals[3]] : $terminals, $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerQuotes(): iterable
    {
        yield 'omit wrapped strings' => ['WITH', 'OMIT', true];
        yield 'keep wrapped strings' => ['WITH', 'KEEP', false];
        yield 'omit unwrapped strings' => ['WITHOUT', 'OMIT', false];
    }

    public function testReturningFormatRemovesOnlyJsonValueOutputFormatAndRetainsNestedJsonInputFormat(): void
    {
        $terminals = [
            new TerminalOccurrence('JSON_VALUE', 10, [0], ['func_expr_common_subexpr']),
            new TerminalOccurrence('FORMAT', 11, [0, 1, 2], ['func_expr_common_subexpr', 'json_returning_clause_opt', 'json_format_clause_opt']),
            new TerminalOccurrence('FORMAT', 12, [0, 3, 4], ['func_expr_common_subexpr', 'json_value_expr', 'json_format_clause_opt']),
        ];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, 'func_expr_common_subexpr', 0),
            new ProductionOccurrence(1, 0, 'json_returning_clause_opt', 0),
            new ProductionOccurrence(2, 1, 'json_format_clause_opt', 0),
            new ProductionOccurrence(3, 0, 'json_value_expr', 0),
            new ProductionOccurrence(4, 3, 'json_format_clause_opt', 0),
            new ProductionOccurrence(5, null, 'func_expr_common_subexpr', 0),
        ]);
        $rule = new JsonOptionsRule();
        $result = $rule->returningFormat($input, 0);
        self::assertEquals($result, $rule->rewrite($input));
        self::assertSame([$terminals[0], $terminals[2]], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }
}
