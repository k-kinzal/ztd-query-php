<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\CopySourceRule;

#[CoversClass(CopySourceRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class CopySourceRuleTest extends TestCase
{
    public function testRewriteReplacesProgramStdinAndRemovesOnlyDirectCopyToWhere(): void
    {
        $trace = new DerivationTrace('CopyStmt');
        $trace->expand(0, new Production([new NonTerminal('copy_from'), new NonTerminal('opt_program'), new NonTerminal('copy_file_name'), new NonTerminal('where_clause'), new NonTerminal('query')]), 0);
        $trace->expand(0, new Production([new Terminal('TO')]), 1);
        $trace->expand(1, new Production([new Terminal('PROGRAM')]), 1);
        $trace->expand(2, new Production([new Terminal('STDIN')]), 1);
        $trace->expand(3, new Production([new Terminal('WHERE'), new Terminal('ICONST')]), 1);
        $trace->expand(5, new Production([new NonTerminal('where_clause')]), 0);
        $trace->expand(5, new Production([new Terminal('WHERE'), new Terminal('ICONST')]), 1);
        $input = $trace->terminals();
        $result = (new CopySourceRule())->rewrite($input);
        self::assertSame(['TO', 'PROGRAM', 'SCONST', 'WHERE', 'ICONST'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->terminals[5], $result->terminals[3]);
    }

    public function testRewritePreservesCopyFromStdinWithoutProgram(): void
    {
        $trace = new DerivationTrace('CopyStmt');
        $trace->expand(0, new Production([new NonTerminal('copy_from'), new NonTerminal('opt_program'), new NonTerminal('copy_file_name'), new NonTerminal('where_clause')]), 0);
        $trace->expand(0, new Production([new Terminal('FROM')]), 0);
        $trace->expand(1, new Production([]), 0);
        $trace->expand(1, new Production([new Terminal('STDIN')]), 1);
        $trace->expand(2, new Production([new Terminal('WHERE'), new Terminal('ICONST')]), 1);
        $input = $trace->terminals();
        self::assertSame($input, (new CopySourceRule())->rewrite($input));
    }
}
