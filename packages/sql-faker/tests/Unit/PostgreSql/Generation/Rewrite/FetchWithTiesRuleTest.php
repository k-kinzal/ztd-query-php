<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testRewriteAddsOrderingForTheSameQuery(): void
    {
        $trace = new DerivationTrace('select_no_parens');
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('ICONST'), new NonTerminal('limit_clause')]), 0);
        $trace->expand(2, new Production([new Terminal('FETCH'), new Terminal('FIRST_P'), new Terminal('ROW'), new Terminal('WITH'), new Terminal('TIES')]), 0);
        $input = $trace->terminals();
        $result = (new FetchWithTiesRule())->rewrite($input);
        self::assertSame(['SELECT', 'ICONST', 'ORDER', 'BY', 'ICONST', 'FETCH', 'FIRST_P', 'ROW', 'WITH', 'TIES'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testOrderedPreservesAnExistingSortClause(): void
    {
        $terminal = new TerminalOccurrence('ORDER', 2, [0, 1], ['select_no_parens', 'sort_clause']);
        $input = new TerminalSequence([$terminal]);
        self::assertSame($input, (new FetchWithTiesRule())->ordered($input, 0, 0));
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
