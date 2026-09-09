<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Query\IntoClauseRule;

#[CoversClass(IntoClauseRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class IntoClauseRuleTest extends TestCase
{
    #[DataProvider('providerContexts')]
    public function testRewritePreservesOnlyTopLevelSelectDestinations(string $scope, bool $kept): void
    {
        $select = new TerminalOccurrence('SELECT', 10, [0, 1], [$scope, 'simple_select']);
        $into = new TerminalOccurrence('INTO', 11, [0, 1, 2], [$scope, 'simple_select', 'into_clause']);
        $name = new TerminalOccurrence('IDENT', 12, [0, 1, 2], [$scope, 'simple_select', 'into_clause']);
        $tail = new TerminalOccurrence('TAIL', 13);
        $terminals = [$select, $into, $name, $tail];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'simple_select', 0),
            new ProductionOccurrence(2, 1, 'into_clause', 0),
        ]);
        $rule = new IntoClauseRule();
        $result = $rule->rewrite($input);
        self::assertSame($kept ? $terminals : [$select, $tail], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerContexts(): iterable
    {
        foreach (['parse_toplevel', 'stmtmulti', 'toplevel_stmt', 'stmt', 'SelectStmt', 'select_no_parens', 'select_with_parens', 'select_clause', 'simple_select', 'ExplainStmt', 'ExplainableStmt'] as $scope) {
            yield [$scope, true];
        }
        foreach (['common_table_expr', 'c_expr', 'table_ref', 'DeclareCursorStmt', 'InsertStmt', 'CreateTableAsStmt', 'PrepareStmt', 'rule_action_stmt'] as $scope) {
            yield [$scope, false];
        }
    }

    public function testRewriteRetainsOnlyTheLeftmostSetOperandDestination(): void
    {
        $left = new TerminalOccurrence('INTO', 10, [0, 1, 2, 3], ['simple_select', 'select_clause', 'simple_select', 'into_clause']);
        $right = new TerminalOccurrence('INTO', 11, [0, 4, 5, 6], ['simple_select', 'select_clause', 'simple_select', 'into_clause']);
        $input = new TerminalSequence([$left, $right], [$left, $right], [], [
            new ProductionOccurrence(0, null, 'simple_select', 0),
            new ProductionOccurrence(1, 0, 'select_clause', 0), new ProductionOccurrence(2, 1, 'simple_select', 0),
            new ProductionOccurrence(3, 2, 'into_clause', 0), new ProductionOccurrence(4, 0, 'select_clause', 0),
            new ProductionOccurrence(5, 4, 'simple_select', 0), new ProductionOccurrence(6, 5, 'into_clause', 0),
        ]);
        $result = (new IntoClauseRule())->rewrite($input);
        self::assertSame([$left], $result->terminals);
        self::assertSame($input->original, $result->original);
    }
}
