<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\CoverageSets;
use SqlFaker\Coverage\CoverageSnapshotStore;
use SqlFaker\Coverage\GenerationTrace;
use SqlFaker\Coverage\GeneratorRevision;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Coverage\LexicalObservation;
use SqlFaker\Coverage\SnapshotValidation;
use SqlFaker\Generation\Choice\ByteChoices;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\DerivationNode;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(CoverageSnapshotStore::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(GrammarCoverageInventory::class)]
#[UsesClass(CoverageException::class)]
#[UsesClass(GrammarCoverage::class)]
#[UsesClass(GeneratorRevision::class)]
#[UsesClass(SnapshotValidation::class)]
#[UsesClass(GenerationTrace::class)]
#[UsesClass(CoverageSets::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(DerivationNode::class)]
#[UsesClass(LexicalObservation::class)]
final class CoverageSnapshotStoreTest extends TestCase
{
    public function testReadReturnsNoHistoryWhenTheKeyHasNoSnapshot(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $store = new CoverageSnapshotStore($directory, 'key');
        self::assertNull($store->read());
        unset($store);
        (new Filesystem())->remove($directory);
    }

    public function testWriteReplacesACompleteSnapshotAndIgnoresInterruptedTemporaryFiles(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $store = new CoverageSnapshotStore($directory, 'key');
        $store->write('{"old":true}');
        file_put_contents($directory . '/interrupted-temporary', '{"partial":');
        self::assertSame('{"old":true}', $store->read());
        $store->write('{"new":true}');
        self::assertSame('{"new":true}', $store->read());
        unset($store);
        (new Filesystem())->remove($directory);
    }

    public function testWriteRejectsASecondWriterForTheSameKey(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $store = new CoverageSnapshotStore($directory, 'key');
        $this->expectException(CoverageException::class);
        try {
            new CoverageSnapshotStore($directory, 'key');
        } finally {
            unset($store);
            (new Filesystem())->remove($directory);
        }
    }
}
