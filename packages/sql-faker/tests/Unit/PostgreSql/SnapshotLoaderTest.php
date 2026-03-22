<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spec\Runner\GrammarContractChecker;
use Spec\Support\GrammarClaimLoader;
use Spec\Support\GrammarEvidenceAssert;
use SqlFaker\PostgreSql\SnapshotLoader;

#[CoversNothing]
final class SnapshotLoaderTest extends TestCase
{
    public function testSnapshotLoaderProjectsConfiguredPostgreSqlSnapshotIntoContractGrammar(): void
    {
        $loader = new SnapshotLoader('pg-17.2');
        $snapshot = $loader->load();

        self::assertSame('pg-17.2', $loader->version());
        self::assertNotSame('', $snapshot->startSymbol);
        self::assertNotNull($snapshot->rule('SelectStmt'));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    #[DataProvider('providerSnapshotContractEvidence')]
    public function testSnapshotLoaderSatisfiesPostgreSqlSnapshotClaims(array $evidence, string $claimId): void
    {
        $snapshot = (new SnapshotLoader('pg-17.2'))->load();

        GrammarEvidenceAssert::assert(
            $snapshot,
            new GrammarContractChecker($snapshot),
            $evidence,
            $claimId,
            'pg-17.2',
        );
    }

    public function testSnapshotLoaderFailsForUnknownSnapshotVersion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grammar file not found');

        (new SnapshotLoader('__missing_postgresql_snapshot__'))->load();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerSnapshotContractEvidence(): iterable
    {
        foreach (GrammarClaimLoader::loadGrammarEvidence(__DIR__ . '/../../../spec/claims/postgresql.contract.json', ['snapshot']) as $index => $case) {
            yield sprintf('%s #%d', $case['claim_id'], $index) => [$case['evidence'], $case['claim_id']];
        }
    }
}
