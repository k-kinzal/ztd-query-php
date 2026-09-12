<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\WithoutRowidRule;

#[CoversClass(WithoutRowidRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
final class WithoutRowidRuleTest extends TestCase
{
    public function testRewriteCompletesAKeyOnlyForWithoutRowidAndRetainsTheOriginalTrace(): void
    {
        $trace = new DerivationTrace('create_table_args');
        $trace->expand(0, new Production([new NonTerminal('column'), new Terminal('ROWID_TABLE_OPTION')]), 0);
        $trace->expand(0, new Production([new NonTerminal('columnname'), new NonTerminal('carglist')]), 0);
        $trace->expand(0, new Production([new Terminal('ID')]), 0);
        $trace->expand(1, new Production([]), 0);
        $input = $trace->terminals();
        $result = (new WithoutRowidRule())->rewrite($input);
        self::assertSame(['ID', 'PRIMARY', 'KEY', 'ROWID_TABLE_OPTION'], $result->names());
        self::assertSame(['create_table_args', 'column', 'carglist'], $result->terminals[1]->rules);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
    }

    public function testRewriteRemovesAutoincrementAndPreservesAnExistingPrimaryKey(): void
    {
        $trace = new DerivationTrace('create_table_args');
        $trace->expand(0, new Production([new Terminal('ID'), new Terminal('PRIMARY'), new Terminal('KEY'), new NonTerminal('autoinc'), new Terminal('ROWID_TABLE_OPTION')]), 0);
        $trace->expand(3, new Production([new Terminal('AUTOINCR')]), 1);
        self::assertSame(['ID', 'PRIMARY', 'KEY', 'ROWID_TABLE_OPTION'], (new WithoutRowidRule())->rewrite($trace->terminals())->names());
    }

    public function testPrimaryKeyLeavesASequenceWithoutAColumnUntouched(): void
    {
        $input = TerminalSequence::fromNames(['ID']);
        self::assertSame($input, (new WithoutRowidRule())->primaryKey($input, 9));
        self::assertSame($input, (new WithoutRowidRule())->rewrite($input));
    }
}
