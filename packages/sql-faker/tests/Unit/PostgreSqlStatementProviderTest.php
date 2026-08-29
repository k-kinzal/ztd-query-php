<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\LexicalGrammar;
use SqlFaker\PostgreSqlStatementProvider;

#[Medium]
#[CoversClass(PostgreSqlStatementProvider::class)]
final class PostgreSqlStatementProviderTest extends TestCase
{
    #[DataProvider('providerTargetedGenerationSeed')]
    public function testPartitionOfStatementGeneratesRangeChildDdl(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->partitionOfStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->partitionOfStatement(40));
        self::assertContains('PARTITION', $tokens);
        self::assertContains('FROM', $tokens);
        self::assertContains('TO', $tokens);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerTargetedGenerationSeed(): iterable
    {
        foreach (range(0, 31) as $seed) {
            yield "seed {$seed}" => [$seed];
        }
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testInsertFunctionUpsertStatementDerivesFunctionExpressionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->insertFunctionUpsertStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))
            ->tokenize($sql);
        $values = array_search('VALUES', $tokens, true);
        $conflict = array_search('CONFLICT', $tokens, true);
        $set = array_search('SET', $tokens, true);
        $functionOpen = array_search('(', array_slice($tokens, (int) $set, null, true), true);

        $faker->seed($seed);
        self::assertSame($sql, $provider->insertFunctionUpsertStatement(40));
        self::assertIsInt($values);
        self::assertIsInt($conflict);
        self::assertIsInt($set);
        self::assertIsInt($functionOpen);
        self::assertLessThan($conflict, $values);
        self::assertGreaterThan($set, $functionOpen);
        self::assertContains('UPDATE', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testPartialIndexUpsertStatementIncludesArbiterPredicate(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->partialIndexUpsertStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->partialIndexUpsertStatement(40));
        $conflict = array_search('CONFLICT', $tokens, true);
        $where = array_search('WHERE', $tokens, true);
        $update = array_search('UPDATE', $tokens, true);
        self::assertIsInt($conflict);
        self::assertIsInt($where);
        self::assertIsInt($update);
        self::assertGreaterThan($conflict, $where);
        self::assertGreaterThan($where, $update);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testFullTextSearchStatementDerivesMatchOperatorFromGrammarAndLexer(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->fullTextSearchStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))->tokenize($sql);
        $where = array_search('WHERE', $tokens, true);

        $faker->seed($seed);
        self::assertSame($sql, $provider->fullTextSearchStatement(40));
        self::assertSame('SELECT', $tokens[0]);
        self::assertContains('FROM', $tokens);
        self::assertIsInt($where);
        self::assertSame(['IDENT', 'Op', 'IDENT'], array_slice($tokens, $where + 1, 3));
        self::assertStringContainsString('@@', $sql);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testTemporaryTableStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->temporaryTableStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->temporaryTableStatement(40));
        self::assertSame('CREATE', $tokens[0]);
        self::assertContains('TEMP', $tokens);
        self::assertContains('TABLE', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testViewStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->viewStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->viewStatement(40));
        self::assertSame('CREATE', $tokens[0]);
        self::assertContains('VIEW', $tokens);
        self::assertNotSame([], array_intersect(['SELECT', 'VALUES', 'TABLE'], $tokens));
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testGeneratedColumnStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->generatedColumnStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->generatedColumnStatement(40));
        self::assertContains('GENERATED', $tokens);
        self::assertContains('STORED', $tokens);
        self::assertContains('AS', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testForeignKeyCascadeStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->foreignKeyCascadeStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->foreignKeyCascadeStatement(40));
        self::assertContains('FOREIGN', $tokens);
        self::assertContains('REFERENCES', $tokens);
        self::assertStringContainsString('ON UPDATE CASCADE', implode(' ', $tokens));
        self::assertStringContainsString('ON DELETE_P CASCADE', implode(' ', $tokens));
    }

    public function testSelectStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->selectStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertMatchesRegularExpression('/SELECT|VALUES|TABLE/', $result);
    }

    public function testInsertStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->insertStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('INSERT', $result);
    }

    public function testUpdateStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->updateStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('UPDATE', $result);
    }

    public function testDeleteStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->deleteStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('DELETE', $result);
    }

    public function testCreateTableStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->createTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('TABLE', $result);
    }

    public function testCreateTableAsStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->createTableAsStatement(maxDepth: 8);

        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('TABLE', $result);
        self::assertStringContainsString('AS', $result);
        self::assertMatchesRegularExpression('/\b(?:SELECT|VALUES|TABLE)\b/', $result);
    }

    public function testCreateDomainStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->createDomainStatement(maxDepth: 8);

        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('DOMAIN', $result);
    }

    public function testAlterTableStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->alterTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('ALTER', $result);
    }

    public function testDropTableStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->dropTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('DROP', $result);
    }

    public function testTruncateStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->truncateStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('TRUNCATE', $result);
    }

    public function testCreateIndexStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->createIndexStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('INDEX', $result);
    }

    public function testTransactionStatement(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->transactionStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertMatchesRegularExpression('/BEGIN|COMMIT|ROLLBACK|ABORT|END|START|SAVEPOINT|RELEASE|PREPARE/', $result);
    }

    public function testForeignKeyConstraint(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker, 'pg-17.2');
        $faker->seed(12345);

        $result = $provider->foreignKeyConstraint(1);

        self::assertSame(
            ['CONSTRAINT', 'IDENT', 'FOREIGN', 'KEY', '(', 'IDENT', ')', 'REFERENCES', 'IDENT', '(', 'IDENT', ')'],
            (new LexicalGrammar($faker, 'pg-17.2'))->tokenize($result),
        );
    }

    public function testSelectContainsSelectOrValuesOrTable(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $sql = $provider->selectStatement(maxDepth: 6);

        self::assertMatchesRegularExpression('/SELECT|VALUES|TABLE/', $sql);
    }

    public function testUpdateContainsSetClause(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->updateStatement(maxDepth: 6);

        self::assertStringContainsString('UPDATE', $result);
        self::assertStringContainsString('SET', $result);
    }

    public function testDeleteContainsFromKeyword(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->deleteStatement(maxDepth: 6);

        self::assertStringContainsString('DELETE', $result);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testTableSampleStatementDerivesSamplingClauseFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->tableSampleStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->tableSampleStatement(40));
        self::assertSame('SELECT', $tokens[0]);
        self::assertContains('FROM', $tokens);
        self::assertContains('TABLESAMPLE', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testDoStatementDerivesAnonymousBlockFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->doStatement();
        $faker->seed($seed);

        self::assertSame($sql, $provider->doStatement(40));
        self::assertSame(
            ['DO', 'SCONST'],
            (new LexicalGrammar($faker, 'pg-17.2', true))->tokenize($sql),
        );
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testMergeStatementDerivesEveryActionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->mergeStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2', true))->tokenize($sql);
        $normalized = implode(' ', $tokens);
        $delete = strpos($normalized, 'WHEN MATCHED THEN DELETE_P');
        $nothing = strpos($normalized, 'WHEN MATCHED THEN DO NOTHING');
        $update = strpos($normalized, 'WHEN MATCHED THEN UPDATE');
        $insert = strpos($normalized, 'WHEN NOT MATCHED THEN INSERT');

        $faker->seed($seed);
        self::assertSame($sql, $provider->mergeStatement(40));
        self::assertContains('MERGE', $tokens);
        self::assertGreaterThanOrEqual(
            4,
            count(array_filter($tokens, static fn (string $token): bool => $token === 'WHEN')),
        );
        self::assertIsInt($delete);
        self::assertIsInt($nothing);
        self::assertIsInt($update);
        self::assertIsInt($insert);
        self::assertLessThan($nothing, $delete);
        self::assertLessThan($update, $nothing);
        self::assertLessThan($insert, $update);
    }

    public function testCopyStatementUsesOfficialGrammarRule(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(12345);

        self::assertStringContainsString('COPY', $provider->copyStatement(maxDepth: 8));
    }

    #[DataProvider('providerNullableSimpleStatementSeed')]
    public function testSimpleStatementReturnsNonEmpty(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);

        self::assertNotSame('', $provider->simpleStatement(maxDepth: 20));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerNullableSimpleStatementSeed(): iterable
    {
        yield 'PHP 8.1 and 8.2 random mode' => [252];
        yield 'PHP 8.3 and later random mode' => [68];
    }

    public function testSimpleStatementReturnsNonEmptyAtMinimumDepth(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed(0);

        self::assertNotSame('', $provider->simpleStatement(maxDepth: 1));
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testDomainDmlStatementDerivesDmlFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->domainDmlStatement();
        $faker->seed($seed);
        $lexer = new LexicalGrammar($faker, 'pg-17.2', true);

        $tokens = $lexer->tokenize($sql);

        self::assertSame($sql, $provider->domainDmlStatement(40));
        self::assertContains($tokens[0], ['INSERT', 'UPDATE', 'DELETE_P']);
    }


    public function testMultipleGenerationsReturnDifferentResults(): void
    {
        $faker1 = Factory::create();
        $faker1->seed(1);
        $provider1 = new PostgreSqlStatementProvider($faker1);
        $sql1 = $provider1->selectStatement(maxDepth: 3);

        $faker2 = Factory::create();
        $faker2->seed(2);
        $provider2 = new PostgreSqlStatementProvider($faker2);
        $sql2 = $provider2->selectStatement(maxDepth: 3);

        self::assertNotSame($sql1, $sql2, 'Different seeds should produce different SQL');
    }
}
