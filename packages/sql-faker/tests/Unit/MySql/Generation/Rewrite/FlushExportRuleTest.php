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
use SqlFaker\MySql\Generation\Rewrite\FlushExportRule;

#[CoversClass(FlushExportRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class FlushExportRuleTest extends TestCase
{
    public function testRewriteAddsATableToAnEmptyExportListAndPreservesItsProvenance(): void
    {
        $trace = new DerivationTrace('flush_options');
        $trace->expand(0, new Production([new Terminal('TABLES'), new NonTerminal('opt_table_list'), new NonTerminal('opt_flush_lock')]), 0);
        $trace->expand(1, new Production([]), 0);
        $trace->expand(1, new Production([new Terminal('FOR_SYM'), new Terminal('EXPORT')]), 1);
        $input = $trace->terminals();
        $result = (new FlushExportRule())->rewrite($input);
        self::assertSame(['TABLES', 'IDENT_QUOTED', 'FOR_SYM', 'EXPORT'], $result->names());
        self::assertTrue($result->terminals[1]->within('opt_table_list'));
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testRewriteLeavesGlobalReadLocksValidWithoutTables(): void
    {
        $trace = new DerivationTrace('flush_options');
        $trace->expand(0, new Production([new Terminal('TABLES'), new NonTerminal('opt_table_list'), new NonTerminal('opt_flush_lock')]), 0);
        $trace->expand(1, new Production([]), 0);
        $trace->expand(1, new Production([new Terminal('WITH'), new Terminal('READ_SYM'), new Terminal('LOCK_SYM')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new FlushExportRule())->rewrite($input));
    }
}
