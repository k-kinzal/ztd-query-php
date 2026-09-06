<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\CoverageSnapshotStore;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ChoiceSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
final class CoverageSnapshotStoreTest extends TestCase
{
    public function testReadReturnsNoHistoryWhenTheKeyHasNoSnapshot(): void
    {
        $directory = CoverageFixture::directory();
        $store = new CoverageSnapshotStore($directory, 'key');
        self::assertNull($store->read());
        unset($store);
        CoverageFixture::remove($directory);
    }

    public function testWriteReplacesACompleteSnapshotAndIgnoresInterruptedTemporaryFiles(): void
    {
        $directory = CoverageFixture::directory();
        $store = new CoverageSnapshotStore($directory, 'key');
        $store->write('{"old":true}');
        file_put_contents($directory . '/interrupted-temporary', '{"partial":');
        self::assertSame('{"old":true}', $store->read());
        $store->write('{"new":true}');
        self::assertSame('{"new":true}', $store->read());
        unset($store);
        CoverageFixture::remove($directory);
    }

    public function testWriteRejectsASecondWriterForTheSameKey(): void
    {
        $directory = CoverageFixture::directory();
        $store = new CoverageSnapshotStore($directory, 'key');
        $this->expectException(CoverageException::class);
        try {
            new CoverageSnapshotStore($directory, 'key');
        } finally {
            unset($store);
            CoverageFixture::remove($directory);
        }
    }
}
