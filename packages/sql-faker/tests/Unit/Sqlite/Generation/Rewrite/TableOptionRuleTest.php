<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\TableOptionRule;

#[CoversClass(TableOptionRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class TableOptionRuleTest extends TestCase
{
    public function testRewriteConstrainsOnlyTheTwoTableOptionPositions(): void
    {
        $trace = new DerivationTrace('table');
        $trace->expand(0, new Production([new Terminal('ID'), new NonTerminal('table_option'), new NonTerminal('table_option')]), 0);
        $trace->expand(1, new Production([new Terminal('ID')]), 0);
        $trace->expand(2, new Production([new Terminal('WITHOUT'), new Terminal('ID')]), 1);
        $input = $trace->terminals();
        $result = (new TableOptionRule())->rewrite($input);
        self::assertSame(['ID', 'STRICT_TABLE_OPTION', 'WITHOUT', 'ROWID_TABLE_OPTION'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
    }

    public function testRewriteLeavesUnrelatedAndEmptyScopesUntouched(): void
    {
        $input = new TerminalSequence([new TerminalOccurrence('ID', 0)], [], [], [new ProductionOccurrence(1, null, 'table_option', 0)]);
        self::assertSame($input, (new TableOptionRule())->rewrite($input));
    }
}
