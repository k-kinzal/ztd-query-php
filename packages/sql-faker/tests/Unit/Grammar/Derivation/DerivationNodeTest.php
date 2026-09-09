<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationNode;
use SqlFaker\Grammar\NonTerminal;

#[CoversClass(DerivationNode::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\LexicalObservation::class)]
final class DerivationNodeTest extends TestCase
{
    public function testSymbolRetainsAnOccurrenceWithoutChangingTheGrammarSymbol(): void
    {
        $symbol = new NonTerminal('expr');
        $left = new DerivationNode($symbol, 1, 0, 0);
        $right = new DerivationNode($symbol, 3, 0, 2);
        self::assertSame($left->symbol, $right->symbol);
        self::assertNotSame($left->nodeId, $right->nodeId);
        self::assertSame(0, $right->parentNodeId);
        self::assertSame(2, $right->rhsPosition);
    }
}
