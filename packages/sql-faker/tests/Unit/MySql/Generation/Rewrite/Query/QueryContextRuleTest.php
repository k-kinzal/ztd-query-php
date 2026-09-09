<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Query\QueryContextRule;

#[CoversClass(QueryContextRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class QueryContextRuleTest extends TestCase
{
    #[DataProvider('providerOptions')]
    public function testRewriteRestrictsOnlyNestedSelectOptions(string $name, string $scope, string $role, bool $remove): void
    {
        $option = new TerminalOccurrence($name, 1, [0, 2], [$scope, $role]);
        $input = new TerminalSequence([$option], [$option]);
        $result = (new QueryContextRule())->rewrite($input);
        self::assertSame($remove ? [] : [$option], $result->terminals);
        self::assertSame($input->original, $result->original);
    }

    /**
     * @return list<array{string, string, string, bool}>
     */
    public static function providerOptions(): array
    {
        return [
            ['SQL_BUFFER_RESULT', 'subquery', 'select_option', true],
            ['SQL_CALC_FOUND_ROWS', 'subquery', 'select_option', true],
            ['HIGH_PRIORITY', 'subquery', 'select_option', true],
            ['SQL_BIG_RESULT', 'subquery', 'select_option', false],
            ['HIGH_PRIORITY', 'select_stmt', 'select_option', false],
            ['HIGH_PRIORITY', 'subquery', 'ident', false],
        ];
    }

    #[DataProvider('providerSources')]
    public function testRewriteSeparatesCreateTableQuerySources(string $first, bool $insert): void
    {
        $query = new TerminalOccurrence($first, 1, [0], ['as_create_query_expression']);
        $input = new TerminalSequence([$query], [$query], [], [new ProductionOccurrence(0, null, 'as_create_query_expression', 1), new ProductionOccurrence(9, null, 'as_create_query_expression', 0)]);
        $rule = new QueryContextRule();
        $result = $rule->rewrite($input);
        self::assertSame($insert ? ['AS', $first] : [$first], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($query, $result->terminals[count($result->terminals) - 1]);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerSources(): array
    {
        return [['(', true], ['SELECT_SYM', true], ['AS', false]];
    }
}
