<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\IntegerContextRule;

#[CoversClass(IntegerContextRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class IntegerContextRuleTest extends TestCase
{
    public function testRewriteReplacesOnlyDiagnosticDecimalAlternatives(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('dec_num_error'), new Terminal('DECIMAL_NUM')]), 0);
        $trace->expand(0, new Production([new Terminal('DECIMAL_NUM')]), 0);
        $input = $trace->terminals();
        $result = (new IntegerContextRule())->rewrite($input);
        self::assertSame(['NUM', 'DECIMAL_NUM'], $result->names());
        self::assertSame($input->terminals[0]->id, $result->terminals[0]->id);
        self::assertSame($input->original, $result->original);
    }

    public function testRewriteConstrainsTheCheckedReplicationFlag(): void
    {
        $trace = new DerivationTrace('source_def');
        $trace->expand(0, new Production([new Terminal('SOURCE_CONNECTION_AUTO_FAILOVER_SYM'), new Terminal('EQ'), new NonTerminal('real_ulong_num')]), 0);
        $trace->expand(2, new Production([new Terminal('NUM')]), 0);
        self::assertSame(['SOURCE_CONNECTION_AUTO_FAILOVER_SYM', 'EQ', 'REPLICATION_FLAG_NUMBER'], (new IntegerContextRule())->rewrite($trace->terminals())->names());
    }

    public function testMappedKeepsCompatiblePlanOccurrenceIdentity(): void
    {
        $trace = new DerivationTrace('number');
        $trace->expand(0, new Production([new Terminal('NUM')]), 0);
        $input = $trace->terminals();
        $result = (new IntegerContextRule())->mapped($input, 0, 'FLAG', 'source');
        self::assertSame($input->terminals[0]->id, $result->terminals[0]->id);
        self::assertSame($input, (new IntegerContextRule())->mapped($input, 999, 'FLAG', 'source'));
    }
    public function testRewritePreservesDefaultAndRestrictsOnlyTheNumericTernaryOption(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('ternary_option'), new NonTerminal('ternary_option')]), 0);
        $trace->expand(0, new Production([new NonTerminal('ulong_num')]), 0);
        $trace->expand(0, new Production([new Terminal('LONG_NUM')]), 2);
        $trace->expand(1, new Production([new Terminal('DEFAULT_SYM')]), 1);
        self::assertSame(['TERNARY_OPTION_NUMBER', 'DEFAULT_SYM'], (new IntegerContextRule())->rewrite($trace->terminals())->names());
    }
}
