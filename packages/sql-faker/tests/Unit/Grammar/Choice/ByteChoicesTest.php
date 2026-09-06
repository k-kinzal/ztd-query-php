<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Choice\ByteChoices;

#[CoversClass(ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ChoiceSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
final class ByteChoicesTest extends TestCase
{
    public function testIndexConsumesLittleEndianChoicesWiderThanOneByte(): void
    {
        $choices = new ByteChoices("\x00\x01\xff");
        self::assertSame(256, $choices->index(300));
        self::assertSame(0, $choices->index(3));
        self::assertNull($choices->index(2));
    }

    public function testIndexDoesNotUseAnIncompleteChoice(): void
    {
        $choices = new ByteChoices("\xff");
        self::assertNull($choices->index(257));
        self::assertNull($choices->index(2));
    }

    public function testWidthUsesTheMinimumNumberOfBytesWithoutAssuming256Alternatives(): void
    {
        self::assertSame(1, ByteChoices::width(1));
        self::assertSame(1, ByteChoices::width(256));
        self::assertSame(2, ByteChoices::width(257));
        self::assertSame(3, ByteChoices::width(65537));
    }


    public function testIndexReducesUnsignedEightByteValuesWithoutIntegerOverflow(): void
    {
        self::assertSame(1, (new ByteChoices(str_repeat("\xff", 8)))->index(PHP_INT_MAX));
    }
}
