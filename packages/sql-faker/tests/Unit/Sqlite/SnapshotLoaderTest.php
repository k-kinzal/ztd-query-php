<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spec\Runner\GrammarContractChecker;
use Spec\Support\GrammarClaimLoader;
use Spec\Support\GrammarEvidenceAssert;
use SqlFaker\Sqlite\SnapshotLoader;

#[CoversNothing]
final class SnapshotLoaderTest extends TestCase
{
    public function testSnapshotLoaderProjectsConfiguredSqliteSnapshotIntoContractGrammar(): void
    {
        $loader = new SnapshotLoader('sqlite-3.47.2');
        $snapshot = $loader->load();

        self::assertSame('sqlite-3.47.2', $loader->version());
        self::assertNotSame('', $snapshot->startSymbol);
        self::assertNotNull($snapshot->rule('selectnowith'));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    #[DataProvider('providerSnapshotContractEvidence')]
    public function testSnapshotLoaderSatisfiesSqliteSnapshotClaims(array $evidence, string $claimId): void
    {
        $snapshot = (new SnapshotLoader('sqlite-3.47.2'))->load();

        GrammarEvidenceAssert::assert(
            $snapshot,
            new GrammarContractChecker($snapshot),
            $evidence,
            $claimId,
            'sqlite-3.47.2',
        );
    }

    public function testSnapshotLoaderFailsForUnknownSnapshotVersion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grammar file not found');

        (new SnapshotLoader('__missing_sqlite_snapshot__'))->load();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerSnapshotContractEvidence(): iterable
    {
        foreach (GrammarClaimLoader::loadGrammarEvidence(__DIR__ . '/../../../spec/claims/sqlite.contract.json', ['snapshot']) as $index => $case) {
            yield sprintf('%s #%d', $case['claim_id'], $index) => [$case['evidence'], $case['claim_id']];
        }
    }
}
