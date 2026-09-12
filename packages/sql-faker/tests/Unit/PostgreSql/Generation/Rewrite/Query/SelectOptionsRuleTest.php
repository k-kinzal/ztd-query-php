<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Query\SelectOptionsRule;

#[CoversClass(SelectOptionsRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class SelectOptionsRuleTest extends TestCase
{
    #[DataProvider('providerOptions')]
    public function testRewriteScopesOptionsToTheSameSelectNode(string $innerRule, string $outerRule, string $boundary, bool $removed): void
    {
        $body = new TerminalOccurrence('SELECT', 10, [0, 1, 2, 3], ['select_no_parens', $boundary, 'select_no_parens', 'simple_select']);
        $inner = new TerminalOccurrence('INNER', 11, [0, 1, 2, 4], ['select_no_parens', $boundary, 'select_no_parens', $innerRule]);
        $outer = new TerminalOccurrence('OUTER', 12, [0, 5], ['select_no_parens', $outerRule]);
        $terminals = [$body, $inner, $outer];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, 'select_no_parens', 0),
            new ProductionOccurrence(1, 0, $boundary, 0),
            new ProductionOccurrence(2, 1, 'select_no_parens', 0),
            new ProductionOccurrence(3, 2, 'simple_select', 0),
            new ProductionOccurrence(4, 2, $innerRule, 0),
            new ProductionOccurrence(5, 0, $outerRule, 0),
        ]);
        $rule = new SelectOptionsRule();
        $result = $rule->rewrite($input);
        self::assertSame($removed ? [$body, $inner] : $terminals, $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, string, string, bool}>
     */
    public static function providerOptions(): iterable
    {
        foreach ([['sort_clause', 'opt_sort_clause'], ['opt_sort_clause', 'sort_clause'], ['select_limit', 'opt_select_limit'], ['opt_select_limit', 'select_limit'], ['with_clause', 'with_clause']] as [$inner, $outer]) {
            foreach (['select_clause', 'select_with_parens'] as $wrapper) {
                yield [$inner, $outer, $wrapper, true];
            }
            foreach (['simple_select', 'from_clause', 'a_expr', 'with_clause'] as $boundary) {
                yield [$inner, $outer, $boundary, false];
            }
        }
        yield ['sort_clause', 'select_limit', 'select_with_parens', false];
        yield ['with_clause', 'sort_clause', 'select_with_parens', false];
    }

    public function testRewriteIgnoresEmptyAndUnscopedClauses(): void
    {
        $input = new TerminalSequence([], [], [], [
            new ProductionOccurrence(0, null, 'select_no_parens', 0),
            new ProductionOccurrence(1, 0, 'opt_sort_clause', 0),
            new ProductionOccurrence(2, null, 'sort_clause', 0),
        ]);
        self::assertSame($input, (new SelectOptionsRule())->rewrite($input));
    }

    public function testQueryFollowsOnlySelectWrappersAndResolvesOptionalClauses(): void
    {
        $nodes = [
            new ProductionOccurrence(0, null, 'SelectStmt', 0),
            new ProductionOccurrence(1, 0, 'select_no_parens', 0),
            new ProductionOccurrence(2, 1, 'select_clause', 0),
            new ProductionOccurrence(3, 2, 'select_with_parens', 0),
            new ProductionOccurrence(4, 3, 'select_no_parens', 0),
            new ProductionOccurrence(5, 4, 'opt_sort_clause', 0),
            new ProductionOccurrence(6, 5, 'sort_clause', 0),
            new ProductionOccurrence(7, 4, 'opt_select_limit', 0),
            new ProductionOccurrence(8, 7, 'select_limit', 0),
        ];
        $rule = new SelectOptionsRule();
        self::assertSame(1, $rule->query($nodes, $nodes[6]));
        self::assertSame(1, $rule->query($nodes, $nodes[8]));
        self::assertNull($rule->query($nodes, $nodes[0]));
        self::assertNull($rule->query($nodes, $nodes[1]));
        self::assertNull($rule->query([], $nodes[6]));
    }
}
