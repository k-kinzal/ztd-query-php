<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spec\Runner\GrammarContractChecker;
use Spec\Support\GrammarClaimLoader;
use Spec\Support\GrammarEvidenceAssert;
use SqlFaker\MySql\SnapshotLoader;

#[CoversNothing]
final class SnapshotLoaderTest extends TestCase
{
    public function testSnapshotLoaderProjectsConfiguredMySqlSnapshotIntoContractGrammar(): void
    {
        $loader = new SnapshotLoader('mysql-8.4.7');
        $snapshot = $loader->load();

        self::assertSame('mysql-8.4.7', $loader->version());
        self::assertNotSame('', $snapshot->startSymbol);
        self::assertNotNull($snapshot->rule('rollback'));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    #[DataProvider('providerSnapshotContractEvidence')]
    public function testSnapshotLoaderSatisfiesMySqlSnapshotClaims(array $evidence, string $claimId): void
    {
        $snapshot = (new SnapshotLoader('mysql-8.4.7'))->load();

        GrammarEvidenceAssert::assert(
            $snapshot,
            new GrammarContractChecker($snapshot),
            $evidence,
            $claimId,
            'mysql-8.4.7',
        );
    }

    public function testSnapshotLoaderFailsForUnknownSnapshotVersion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grammar file not found');

        (new SnapshotLoader('__missing_mysql_snapshot__'))->load();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerSnapshotContractEvidence(): iterable
    {
        foreach (GrammarClaimLoader::loadGrammarEvidence(__DIR__ . '/../../../spec/claims/mysql.contract.json', ['snapshot']) as $index => $case) {
            yield sprintf('%s #%d', $case['claim_id'], $index) => [$case['evidence'], $case['claim_id']];
        }
    }
}
