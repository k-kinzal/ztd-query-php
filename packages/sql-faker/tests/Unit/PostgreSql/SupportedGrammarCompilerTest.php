<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spec\Runner\GrammarContractChecker;
use Spec\Support\GrammarClaimLoader;
use Spec\Support\GrammarEvidenceAssert;
use SqlFaker\Generation\TerminationLengthComputer;
use SqlFaker\PostgreSql\SnapshotLoader;
use SqlFaker\PostgreSql\SupportedGrammarCompiler;

#[CoversNothing]
final class SupportedGrammarCompilerTest extends TestCase
{
    public function testCompileProjectsTheSupportedGrammarIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader('pg-17.2'))->load();
        $supportedGrammar = (new SupportedGrammarCompiler())->compile($snapshot);

        self::assertSame($snapshot->startSymbol, $supportedGrammar->startSymbol);
        self::assertNotNull($supportedGrammar->rule('SelectStmt'));
    }

    public function testRewriteProgramExposesTheDocumentedPostgreSqlStepOrder(): void
    {
        self::assertSame([
            'canonicalize.identifier_like_rules',
            'canonicalize.qualified_names',
            'canonicalize.function_names',
            'filter.indirection_shapes',
            'collapse.row_security_defaults',
            'split.create_partition_of',
            'split.alter_database',
            'restrict.utility_statement_targets',
            'split.alter_table_families',
            'split.alter_index_families',
            'split.alter_view_families',
            'split.alter_sequence_families',
            'split.alter_statistics_families',
            'split.alter_access_method_families',
            'split.alter_domain_families',
            'split.alter_type_families',
            'split.alter_enum_families',
            'restrict.role_and_routine_families',
            'restrict.utility_and_definition_families',
            'rebuild.bounded_dml_and_select_families',
        ], (new SupportedGrammarCompiler())->rewriteProgram()->stepIds());
    }

    /**
     * @param array<string, mixed> $evidence
     */
    #[DataProvider('providerContractGrammarEvidence')]
    public function testCompileSatisfiesPostgreSqlContractClaims(array $evidence, string $claimId): void
    {
        $compiler = new SupportedGrammarCompiler();
        $grammar = $compiler->compile((new SnapshotLoader('pg-17.2'))->load());

        GrammarEvidenceAssert::assert(
            $grammar,
            new GrammarContractChecker($grammar),
            $evidence,
            $claimId,
            'pg-17.2',
            $compiler->rewriteProgram(),
            (new TerminationLengthComputer())->compute($grammar),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerContractGrammarEvidence(): iterable
    {
        foreach (GrammarClaimLoader::loadGrammarEvidence(__DIR__ . '/../../../spec/claims/postgresql.contract.json') as $index => $case) {
            yield sprintf('%s #%d', $case['claim_id'], $index) => [$case['evidence'], $case['claim_id']];
        }
    }
}
