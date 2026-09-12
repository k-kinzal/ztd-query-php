<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationNode;
use SqlFaker\Grammar\Model\NonTerminal;

#[CoversClass(DerivationNode::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Model\Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Model\Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Model\ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Model\Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Choice\ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Coverage\LexicalObservation::class)]
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
