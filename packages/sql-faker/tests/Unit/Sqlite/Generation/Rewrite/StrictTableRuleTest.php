<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\StrictTableRule;

#[CoversClass(StrictTableRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class StrictTableRuleTest extends TestCase
{
    public function testRewriteFillsAnEmptyTypeOnlyInTheSameStrictTable(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('create_table_args'), new NonTerminal('create_table_args')]), 0);
        $trace->expand(0, new Production([new NonTerminal('columnname'), new Terminal('STRICT_TABLE_OPTION')]), 0);
        $trace->expand(0, new Production([new Terminal('ID'), new NonTerminal('typetoken')]), 0);
        $trace->expand(1, new Production([]), 0);
        $trace->expand(2, new Production([new NonTerminal('columnname')]), 0);
        $trace->expand(2, new Production([new Terminal('ID'), new NonTerminal('typetoken')]), 0);
        $trace->expand(3, new Production([]), 0);
        $input = $trace->terminals();
        $result = (new StrictTableRule())->rewrite($input);
        self::assertSame(['ID', 'STRICT_COLUMN_TYPE', 'STRICT_TABLE_OPTION', 'ID'], $result->names());
        self::assertSame(['root', 'create_table_args', 'columnname', 'typetoken'], $result->terminals[1]->rules);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
    }

    public function testRewriteReplacesTheWholeTypeIncludingItsSizeModifiers(): void
    {
        $trace = new DerivationTrace('create_table_args');
        $trace->expand(0, new Production([new NonTerminal('columnname'), new Terminal('STRICT_TABLE_OPTION')]), 0);
        $trace->expand(0, new Production([new Terminal('ID'), new NonTerminal('typetoken')]), 0);
        $trace->expand(1, new Production([new Terminal('ID'), new Terminal('LP'), new Terminal('INTEGER'), new Terminal('RP')]), 2);
        self::assertSame(['ID', 'STRICT_COLUMN_TYPE', 'STRICT_TABLE_OPTION'], (new StrictTableRule())->rewrite($trace->terminals())->names());
    }
}
