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
use SqlFaker\PostgreSql\Generation\Rewrite\HashPartitionBoundRule;

#[CoversClass(HashPartitionBoundRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class HashPartitionBoundRuleTest extends TestCase
{
    public function testRewriteCompletesBothBoundsAndPreservesTheOriginalValue(): void
    {
        $trace = new DerivationTrace('PartitionBoundSpec');
        $trace->expand(0, new Production([new Terminal('('), new NonTerminal('hash_partbound'), new Terminal(')')]), 0);
        $trace->expand(1, new Production([new NonTerminal('hash_partbound_elem')]), 0);
        $trace->expand(1, new Production([new Terminal('IDENT'), new NonTerminal('Iconst')]), 0);
        $trace->expand(2, new Production([new Terminal('ICONST')]), 0);
        $input = $trace->terminals();
        $result = (new HashPartitionBoundRule())->rewrite($input);
        self::assertSame(['(', 'HASH_BOUND_NAME', 'ICONST', ',', 'HASH_BOUND_NAME', 'ICONST', ')'], $result->names());
        self::assertSame($input->terminals[2], $result->terminals[2]);
        self::assertSame($input->original, $result->original);
    }
}
