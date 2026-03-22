<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spec\Runner\GrammarContractChecker;
use Spec\Support\GrammarClaimLoader;
use Spec\Support\GrammarEvidenceAssert;
use SqlFaker\Generation\TerminationLengthComputer;
use SqlFaker\Sqlite\SnapshotLoader;
use SqlFaker\Sqlite\SupportedGrammarCompiler;

#[CoversNothing]
final class SupportedGrammarCompilerTest extends TestCase
{
    public function testCompileProjectsTheSupportedGrammarIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader('sqlite-3.47.2'))->load();
        $supportedGrammar = (new SupportedGrammarCompiler())->compile($snapshot);

        self::assertSame($snapshot->startSymbol, $supportedGrammar->startSymbol);
        self::assertNotNull($supportedGrammar->rule('selectnowith'));
    }

    public function testRewriteProgramExposesTheDocumentedSqliteStepOrder(): void
    {
        self::assertSame([
            'extract.statement_entry_rules',
            'filter.delete_order_by_forms',
            'filter.unsafe_expression_branches',
            'filter.window_branches',
            'filter.keyword_like_identifier_branches',
            'rebuild.create_table',
            'rebuild.attach_detach_vacuum',
            'rebuild.temporary_object_families',
            'rebuild.bounded_select_families',
            'publish.extracted_statement_rules',
        ], (new SupportedGrammarCompiler())->rewriteProgram()->stepIds());
    }

    /**
     * @param array<string, mixed> $evidence
     */
    #[DataProvider('providerContractGrammarEvidence')]
    public function testCompileSatisfiesSqliteContractClaims(array $evidence, string $claimId): void
    {
        $compiler = new SupportedGrammarCompiler();
        $grammar = $compiler->compile((new SnapshotLoader('sqlite-3.47.2'))->load());

        GrammarEvidenceAssert::assert(
            $grammar,
            new GrammarContractChecker($grammar),
            $evidence,
            $claimId,
            'sqlite-3.47.2',
            $compiler->rewriteProgram(),
            (new TerminationLengthComputer())->compute($grammar),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerContractGrammarEvidence(): iterable
    {
        foreach (GrammarClaimLoader::loadGrammarEvidence(__DIR__ . '/../../../spec/claims/sqlite.contract.json') as $index => $case) {
            yield sprintf('%s #%d', $case['claim_id'], $index) => [$case['evidence'], $case['claim_id']];
        }
    }
}
