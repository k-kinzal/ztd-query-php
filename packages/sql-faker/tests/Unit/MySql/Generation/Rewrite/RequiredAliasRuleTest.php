<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\Rewrite\RequiredAliasRule;

#[CoversClass(RequiredAliasRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class RequiredAliasRuleTest extends TestCase
{
    public function testRewriteInsertsAnAliasBeforeTheDerivedColumnList(): void
    {
        $trace = new DerivationTrace('derived_table');
        $trace->expand(0, new Production([new Terminal('QUERY'), new NonTerminal('opt_table_alias'), new NonTerminal('opt_derived_column_list')]), 0);
        $trace->expand(1, new Production([]), 0);
        $trace->expand(1, new Production([new Terminal('('), new Terminal('IDENT'), new Terminal(')')]), 1);
        $input = $trace->terminals();
        $result = (new RequiredAliasRule())->rewrite($input);
        self::assertSame(['QUERY', 'AS', 'IDENT_QUOTED', '(', 'IDENT', ')'], $result->names());
        self::assertSame(['derived_table', 'opt_table_alias'], $result->terminals[1]->rules);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testRewriteKeepsTheCallerSelectedAlias(): void
    {
        $trace = new DerivationTrace('table_function');
        $trace->expand(0, new Production([new Terminal('JSON_TABLE_SYM'), new NonTerminal('opt_table_alias')]), 0);
        $trace->expand(1, new Production([new Terminal('AS'), new Terminal('IDENT')]), 1);
        $input = $trace->terminals();
        self::assertSame($input, (new RequiredAliasRule())->rewrite($input));
    }
}
