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
use SqlFaker\PostgreSql\Generation\Rewrite\LimitOffsetRule;

#[CoversClass(LimitOffsetRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class LimitOffsetRuleTest extends TestCase
{
    public function testRewriteChangesOnlyTheDiagnosticComma(): void
    {
        $trace = new DerivationTrace('limit_clause');
        $trace->expand(0, new Production([new Terminal('LIMIT'), new Terminal('ICONST'), new Terminal(','), new NonTerminal('select_offset_value')]), 1);
        $trace->expand(3, new Production([new Terminal('ICONST')]), 0);
        $input = $trace->terminals();
        $result = (new LimitOffsetRule())->rewrite($input);
        self::assertSame(['LIMIT', 'ICONST', 'OFFSET', 'ICONST'], $result->names());
        self::assertSame($input->terminals[3], $result->terminals[3]);
        self::assertSame($input->original, $result->original);
    }

    public function testRewriteRetainsAnExistingSeparateOffset(): void
    {
        $trace = new DerivationTrace('select_limit');
        $trace->expand(0, new Production([new NonTerminal('limit_clause'), new NonTerminal('offset_clause')]), 0);
        $trace->expand(0, new Production([new Terminal('LIMIT'), new Terminal('ALL'), new Terminal(','), new NonTerminal('select_offset_value')]), 1);
        $trace->expand(3, new Production([new Terminal('REMOVED')]), 0);
        $trace->expand(4, new Production([new Terminal('OFFSET'), new Terminal('KEPT')]), 0);
        self::assertSame(['LIMIT', 'ALL', 'OFFSET', 'KEPT'], (new LimitOffsetRule())->rewrite($trace->terminals())->names());
    }
}
