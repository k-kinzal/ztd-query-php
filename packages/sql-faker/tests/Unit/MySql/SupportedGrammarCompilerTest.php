<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spec\Runner\GrammarContractChecker;
use Spec\Support\GrammarClaimLoader;
use Spec\Support\GrammarEvidenceAssert;
use SqlFaker\Generation\TerminationLengthComputer;
use SqlFaker\MySql\SnapshotLoader;
use SqlFaker\MySql\SupportedGrammarCompiler;

#[CoversNothing]
final class SupportedGrammarCompilerTest extends TestCase
{
    public function testCompileProjectsTheSupportedGrammarIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader('mysql-8.4.7'))->load();
        $supportedGrammar = (new SupportedGrammarCompiler())->compile($snapshot);

        self::assertSame($snapshot->startSymbol, $supportedGrammar->startSymbol);
        self::assertNotNull($supportedGrammar->rule('rollback'));
    }

    public function testRewriteProgramExposesTheDocumentedMySqlStepOrder(): void
    {
        self::assertSame([
            'canonicalize.identifier_entry_points',
            'canonicalize.user',
            'force.alter_event_real_change',
            'enumerate.commit_spellings',
            'enumerate.rollback_spellings',
            'filter.alter_instance_action',
            'filter.bool_pri_all_any_comparison',
            'enumerate.start_transaction_options',
            'split.grant_families',
            'split.revoke_families',
            'canonicalize.clone',
            'bound.table_value_constructor_arity',
            'restrict.sqlstate_literals',
            'split.alter_database_safe_families',
            'restrict.limit_literals',
            'restrict.charset_and_collation_domains',
            'split.set_system_variable_assignments',
            'collapse.replication_option_lists',
            'split.signal_information_items',
            'canonicalize.reset',
            'canonicalize.flush',
            'bound.srs_attributes',
            'restrict.undo_tablespace_diagnostics_explain',
            'restrict.resource_group_cpu_ranges',
            'expand.alter_user_mfa',
        ], (new SupportedGrammarCompiler())->rewriteProgram()->stepIds());
    }

    /**
     * @param array<string, mixed> $evidence
     */
    #[DataProvider('providerContractGrammarEvidence')]
    public function testCompileSatisfiesMySqlContractClaims(array $evidence, string $claimId): void
    {
        $compiler = new SupportedGrammarCompiler();
        $grammar = $compiler->compile((new SnapshotLoader('mysql-8.4.7'))->load());

        GrammarEvidenceAssert::assert(
            $grammar,
            new GrammarContractChecker($grammar),
            $evidence,
            $claimId,
            'mysql-8.4.7',
            $compiler->rewriteProgram(),
            (new TerminationLengthComputer())->compute($grammar),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerContractGrammarEvidence(): iterable
    {
        foreach (GrammarClaimLoader::loadGrammarEvidence(__DIR__ . '/../../../spec/claims/mysql.contract.json') as $index => $case) {
            yield sprintf('%s #%d', $case['claim_id'], $index) => [$case['evidence'], $case['claim_id']];
        }
    }
}
