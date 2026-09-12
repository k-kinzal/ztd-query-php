<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\HashPartitionBoundRule;

#[CoversClass(HashPartitionBoundRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
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
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testRewritePreservesTheFirstTwoValuesAndSurroundingStatements(): void
    {
        $trace = new DerivationTrace('PartitionBoundSpec');
        $trace->expand(0, new Production([new Terminal('PREFIX'), new NonTerminal('hash_partbound'), new Terminal('TAIL')]), 0);
        $trace->expand(1, new Production([new NonTerminal('hash_partbound_elem'), new Terminal(','), new NonTerminal('hash_partbound_elem'), new Terminal(','), new NonTerminal('hash_partbound_elem')]), 0);
        $trace->expand(1, new Production([new Terminal('IDENT'), new NonTerminal('Iconst')]), 0);
        $trace->expand(2, new Production([new Terminal('FIRST')]), 0);
        $trace->expand(4, new Production([new Terminal('IDENT'), new NonTerminal('Iconst')]), 0);
        $trace->expand(5, new Production([new Terminal('SECOND')]), 0);
        $trace->expand(7, new Production([new Terminal('IDENT'), new NonTerminal('Iconst')]), 0);
        $trace->expand(8, new Production([new Terminal('DISCARDED')]), 0);
        $input = $trace->terminals();
        $result = (new HashPartitionBoundRule())->rewrite($input);
        self::assertSame(['PREFIX', 'HASH_BOUND_NAME', 'FIRST', ',', 'HASH_BOUND_NAME', 'SECOND', 'TAIL'], $result->names());
        self::assertSame($input->terminals[2], $result->terminals[2]);
        self::assertSame($input->terminals[5], $result->terminals[5]);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }
}
