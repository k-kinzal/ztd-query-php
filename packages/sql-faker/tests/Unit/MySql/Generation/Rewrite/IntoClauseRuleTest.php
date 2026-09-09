<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\IntoClauseRule;

#[CoversClass(IntoClauseRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class IntoClauseRuleTest extends TestCase
{
    public function testRewriteRemovesOnlySubqueryDestinations(): void
    {
        $inner = new TerminalOccurrence('INTO', 4, [0, 1, 2], ['stmt', 'subquery', 'into_clause']);
        $outer = new TerminalOccurrence('INTO', 5, [0, 3], ['stmt', 'into_clause']);
        $input = new TerminalSequence([$inner, $outer], [$inner, $outer], [], [new ProductionOccurrence(2, 1, 'into_clause', 0), new ProductionOccurrence(3, 0, 'into_clause', 0), new ProductionOccurrence(6, 0, 'into_clause', 0)]);
        $rule = new IntoClauseRule();
        $result = $rule->rewrite($input);
        self::assertSame([$outer], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame(['sql/parse_tree_nodes.cc:PT_subquery:into'], $result->rewrites);
        self::assertSame($result, $rule->rewrite($result));
    }

    #[DataProvider('providerSetOperations')]
    public function testRewritePreservesTheFinalDestinationAcrossSetOperations(string $operator): void
    {
        $first = new TerminalOccurrence('INTO', 10, [0, 1], ['query_expression_body', 'into_clause']);
        $union = new TerminalOccurrence($operator, 11, [0], ['query_expression_body']);
        $last = new TerminalOccurrence('INTO', 12, [0, 2], ['query_expression_body', 'into_clause']);
        $nestedUnion = new TerminalOccurrence('UNION_SYM', 13, [0, 3], ['query_expression_body', 'query_expression_body']);
        $input = new TerminalSequence([$first, $union, $last, $nestedUnion], [$first, $union, $last, $nestedUnion], [], [new ProductionOccurrence(1, 0, 'into_clause', 0), new ProductionOccurrence(2, 0, 'into_clause', 0)]);
        $rule = new IntoClauseRule();
        $result = $rule->rewrite($input);
        self::assertSame([$union, $last, $nestedUnion], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame(['sql/sql_lex.cc:new_set_operation_query:into'], $result->rewrites);
        self::assertSame($result, $rule->rewrite($result));
    }

    public function testRewritePreservesDestinationsBeforeUnrelatedOperatorUses(): void
    {
        $into = new TerminalOccurrence('INTO', 10, [0, 1], ['query_expression_body', 'into_clause']);
        $ordinary = new TerminalOccurrence('SELECT_SYM', 11, [0], ['query_expression_body']);
        $unrelated = new TerminalOccurrence('UNION_SYM', 12, [0], ['other']);
        $unowned = new TerminalOccurrence('UNION_SYM', 13);
        $input = new TerminalSequence([$into, $ordinary, $unrelated, $unowned], [], [], [new ProductionOccurrence(1, 0, 'into_clause', 0)]);
        self::assertSame($input, (new IntoClauseRule())->rewrite($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSetOperations(): iterable
    {
        yield 'union' => ['UNION_SYM'];
        yield 'except' => ['EXCEPT_SYM'];
        yield 'intersect' => ['INTERSECT_SYM'];
    }
}
