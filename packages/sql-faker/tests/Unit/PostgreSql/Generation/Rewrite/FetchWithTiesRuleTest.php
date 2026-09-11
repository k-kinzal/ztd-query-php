<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\FetchWithTiesRule;

#[CoversClass(FetchWithTiesRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class FetchWithTiesRuleTest extends TestCase
{
    #[DataProvider('providerQueryScopes')]
    public function testRewriteAddsOrderingForTheSameQuery(string $scope): void
    {
        $trace = new DerivationTrace($scope);
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('ICONST'), new NonTerminal('limit_clause')]), 0);
        $trace->expand(2, new Production([new Terminal('FETCH'), new Terminal('FIRST_P'), new Terminal('ROW'), new Terminal('WITH'), new Terminal('TIES')]), 0);
        $input = $trace->terminals();
        $result = (new FetchWithTiesRule())->rewrite($input);
        self::assertSame(['SELECT', 'ICONST', 'ORDER', 'BY', 'ICONST', 'FETCH', 'FIRST_P', 'ROW', 'WITH', 'TIES'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    #[DataProvider('providerQueryScopes')]
    public function testOrderedPreservesAnExistingSortClause(string $scope): void
    {
        $terminal = new TerminalOccurrence('ORDER', 2, [0, 1], [$scope, 'sort_clause']);
        $input = new TerminalSequence([$terminal]);
        self::assertSame($input, (new FetchWithTiesRule())->ordered($input, 0, 0));
    }

    #[DataProvider('providerLimitWrappers')]
    public function testOrderedPlacesOrderingBeforeOffsetAndFetch(string $wrapper): void
    {
        $trace = new DerivationTrace('select_no_parens');
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('ICONST'), new NonTerminal('opt_sort_clause'), new NonTerminal($wrapper)]), 0);
        $trace->expand(2, new Production([]), 0);
        $trace->expand(2, new Production([new Terminal('OFFSET'), new Terminal('ICONST'), new NonTerminal('limit_clause')]), 0);
        $trace->expand(4, new Production([new Terminal('FETCH'), new Terminal('FIRST_P'), new Terminal('ROW'), new Terminal('WITH'), new Terminal('TIES')]), 0);
        $input = $trace->terminals();
        $rule = new FetchWithTiesRule();
        $result = $rule->ordered($input, 0, 4);
        self::assertSame(['SELECT', 'ICONST', 'ORDER', 'BY', 'ICONST', 'OFFSET', 'ICONST', 'FETCH', 'FIRST_P', 'ROW', 'WITH', 'TIES'], $result->names());
        self::assertSame($result->names(), $rule->rewrite($input)->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    public function testPlpgsqlOrderingDoesNotSatisfyANestedSelect(): void
    {
        $trace = new DerivationTrace('PLpgSQL_Expr');
        $trace->expand(0, new Production([new Terminal('('), new NonTerminal('select_no_parens'), new Terminal(')'), new NonTerminal('opt_sort_clause')]), 0);
        $trace->expand(3, new Production([new Terminal('ORDER'), new Terminal('BY'), new Terminal('ICONST')]), 0);
        $trace->expand(1, new Production([new Terminal('SELECT'), new Terminal('ICONST'), new NonTerminal('opt_sort_clause'), new NonTerminal('select_limit')]), 0);
        $trace->expand(3, new Production([]), 0);
        $trace->expand(3, new Production([new NonTerminal('limit_clause')]), 0);
        $trace->expand(3, new Production([new Terminal('FETCH'), new Terminal('FIRST_P'), new Terminal('ROW'), new Terminal('WITH'), new Terminal('TIES')]), 0);
        $input = $trace->terminals();
        $rule = new FetchWithTiesRule();
        $result = $rule->rewrite($input);
        self::assertSame(['(', 'SELECT', 'ICONST', 'ORDER', 'BY', 'ICONST', 'FETCH', 'FIRST_P', 'ROW', 'WITH', 'TIES', ')', 'ORDER', 'BY', 'ICONST'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    public function testPlpgsqlFetchDoesNotBorrowOrderingFromANestedSelect(): void
    {
        $trace = new DerivationTrace('PLpgSQL_Expr');
        $trace->expand(0, new Production([new Terminal('('), new NonTerminal('select_no_parens'), new Terminal(')'), new NonTerminal('opt_sort_clause'), new NonTerminal('opt_select_limit')]), 0);
        $trace->expand(4, new Production([new Terminal('OFFSET'), new Terminal('ICONST'), new NonTerminal('limit_clause')]), 0);
        $trace->expand(6, new Production([new Terminal('FETCH'), new Terminal('FIRST_P'), new Terminal('ROW'), new Terminal('WITH'), new Terminal('TIES')]), 0);
        $trace->expand(3, new Production([]), 0);
        $trace->expand(1, new Production([new Terminal('SELECT'), new Terminal('ICONST'), new NonTerminal('sort_clause')]), 0);
        $trace->expand(3, new Production([new Terminal('ORDER'), new Terminal('BY'), new Terminal('ICONST')]), 0);
        $input = $trace->terminals();
        $rule = new FetchWithTiesRule();
        $result = $rule->rewrite($input);
        self::assertSame(['(', 'SELECT', 'ICONST', 'ORDER', 'BY', 'ICONST', ')', 'ORDER', 'BY', 'ICONST', 'OFFSET', 'ICONST', 'FETCH', 'FIRST_P', 'ROW', 'WITH', 'TIES'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerQueryScopes(): array
    {
        return [['select_no_parens'], ['PLpgSQL_Expr']];
    }

    /**
     * @return list<array{string}>
     */
    public static function providerLimitWrappers(): array
    {
        return [['select_limit'], ['opt_select_limit']];
    }
    public function testOrderedPlacesTheNewSortClauseBeforeAnExistingLockingClause(): void
    {
        $trace = new DerivationTrace('select_no_parens');
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('ICONST'), new NonTerminal('opt_sort_clause'), new NonTerminal('for_locking_clause'), new Terminal('FETCH')]), 0);
        $trace->expand(2, new Production([]), 0);
        $trace->expand(2, new Production([new Terminal('FOR'), new Terminal('UPDATE')]), 0);
        $input = $trace->terminals();
        $result = (new FetchWithTiesRule())->ordered($input, 0, 4);
        self::assertSame(['SELECT', 'ICONST', 'ORDER', 'BY', 'ICONST', 'FOR', 'UPDATE', 'FETCH'], $result->names());
        self::assertSame($result, (new FetchWithTiesRule())->ordered($result, 0, 7));
    }

}
