<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\MySql\LexicalGrammar;
use SqlFaker\MySqlStatementProvider;

#[CoversClass(MySqlStatementProvider::class)]
final class MySqlStatementProviderTest extends TestCase
{
    #[DataProvider('providerTargetedGenerationSeed')]
    public function testInsertFunctionUpsertStatementDerivesConditionalExpressionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->insertFunctionUpsertStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($sql);
        $valueTokens = array_intersect($tokens, ['VALUE_SYM', 'VALUES']);
        $values = array_key_first($valueTokens);
        $update = array_search('UPDATE_SYM', $tokens, true);
        $conditional = array_search('IF', $tokens, true);

        $faker->seed($seed);
        self::assertSame($sql, $provider->insertFunctionUpsertStatement(40));
        self::assertIsInt($values);
        self::assertIsInt($update);
        self::assertIsInt($conditional);
        self::assertLessThan($update, $values);
        self::assertGreaterThan($update, $conditional);
        self::assertContains('DUPLICATE_SYM', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testFullTextSearchStatementDerivesMatchExpressionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->fullTextSearchStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($sql);
        $select = array_search('SELECT_SYM', $tokens, true);
        $from = array_search('FROM', $tokens, true);
        $where = array_search('WHERE', $tokens, true);
        $match = array_search('MATCH', $tokens, true);

        $faker->seed($seed);
        self::assertSame($sql, $provider->fullTextSearchStatement(40));
        self::assertIsInt($select);
        self::assertIsInt($from);
        self::assertNotContains('DUAL_SYM', $tokens);
        self::assertIsInt($where);
        self::assertIsInt($match);
        self::assertGreaterThan($select, $from);
        self::assertGreaterThan($from, $match);
        self::assertContains('AGAINST', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testTemporaryTableStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->temporaryTableStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->temporaryTableStatement(40));
        self::assertSame('CREATE', $tokens[0]);
        self::assertContains('TEMPORARY', $tokens);
        self::assertContains('TABLE_SYM', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testViewStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->viewStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->viewStatement(40));
        self::assertSame('CREATE', $tokens[0]);
        self::assertContains('VIEW_SYM', $tokens);
        self::assertContains('SELECT_SYM', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testGeneratedColumnStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->generatedColumnStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->generatedColumnStatement(40));
        self::assertContains('GENERATED', $tokens);
        self::assertContains('ALWAYS_SYM', $tokens);
        self::assertContains('STORED_SYM', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testForeignKeyCascadeStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->foreignKeyCascadeStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->foreignKeyCascadeStatement(40));
        self::assertContains('FOREIGN', $tokens);
        self::assertContains('REFERENCES', $tokens);
        self::assertStringContainsString('ON_SYM UPDATE_SYM CASCADE', implode(' ', $tokens));
        self::assertStringContainsString('ON_SYM DELETE_SYM CASCADE', implode(' ', $tokens));
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testPartitionSelectStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->partitionSelectStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($sql);
        $select = array_search('SELECT_SYM', $tokens, true);
        $from = array_search('FROM', $tokens, true);
        $partition = array_search('PARTITION_SYM', $tokens, true);

        $faker->seed($seed);
        self::assertSame($sql, $provider->partitionSelectStatement(40));
        self::assertIsInt($select);
        self::assertIsInt($from);
        self::assertIsInt($partition);
        self::assertGreaterThan($select, $from);
        self::assertGreaterThan($from, $partition);
    }

    public function testLoadDataStatementUsesOfficialGrammarRule(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(164);

        $result = $provider->loadDataStatement(maxDepth: 8);

        self::assertMatchesRegularExpression('/\bLOAD\b.*\bDATA\b/is', $result);
        self::assertMatchesRegularExpression('/\bINFILE\b/i', $result);
        self::assertMatchesRegularExpression('/\bINTO\b/i', $result);
    }

    public function testSelectStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->selectStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bSELECT\b/i', $result);
    }

    public function testInsertStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->insertStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bINSERT\b/i', $result);
    }

    public function testUpdateStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->updateStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bUPDATE\b/i', $result);
    }

    public function testDeleteStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->deleteStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bDELETE\b/i', $result);
    }

    #[DataProvider('providerMultiTableGenerationSeed')]
    public function testMultiTableUpdateStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($provider->multiTableUpdateStatement(maxDepth: 20));

        self::assertSame('UPDATE_SYM', $tokens[0]);
        $set = array_search('SET_SYM', $tokens, true);
        self::assertIsInt($set);
        self::assertSame(1, count(array_filter(
            array_slice($tokens, 0, $set),
            static fn (string $token): bool => $token === ',',
        )));
        self::assertContains('SET_SYM', $tokens);
    }

    #[DataProvider('providerMultiTableGenerationSeed')]
    public function testMultiTableDeleteStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))
            ->tokenize($provider->multiTableDeleteStatement(maxDepth: 20));

        self::assertSame('DELETE_SYM', $tokens[0]);
        self::assertContains('FROM', $tokens);
        self::assertGreaterThanOrEqual(2, count(array_filter(
            $tokens,
            static fn (string $token): bool => in_array($token, ['IDENT', 'IDENT_QUOTED'], true),
        )));
    }

    public function testCreateTableStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->createTableStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bCREATE\b/i', $result);
        self::assertMatchesRegularExpression('/\bTABLE\b/i', $result);
    }

    public function testAlterTableStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->alterTableStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bALTER\b/i', $result);
        self::assertMatchesRegularExpression('/\bTABLE\b/i', $result);
    }

    public function testDropTableStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->dropTableStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bDROP\b/i', $result);
        self::assertMatchesRegularExpression('/\bTABLE\b/i', $result);
    }

    public function testSimpleStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->simpleStatement(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testReplaceStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->replaceStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bREPLACE\b/i', $result);
    }

    public function testTruncateStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->truncateStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bTRUNCATE\b/i', $result);
    }

    public function testCreateIndexStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->createIndexStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bCREATE\b/i', $result);
        self::assertMatchesRegularExpression('/\bINDEX\b/i', $result);
    }

    public function testDropIndexStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->dropIndexStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bDROP\b/i', $result);
        self::assertMatchesRegularExpression('/\bINDEX\b/i', $result);
    }

    public function testBeginStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->beginStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bBEGIN\b/i', $result);
    }

    public function testCommitStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->commitStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bCOMMIT\b/i', $result);
    }

    public function testRollbackStatement(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->rollbackStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bROLLBACK\b/i', $result);
    }

    #[DataProvider('providerMySqlVersion')]
    public function testForeignKeyConstraint(string $version): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker, $version);
        $faker->seed(12345);

        $result = $provider->foreignKeyConstraint(1);

        self::assertSame(
            ['CONSTRAINT', 'IDENT', 'FOREIGN', 'KEY_SYM', '(', 'IDENT', ')', 'REFERENCES', 'IDENT', '(', 'IDENT', ')'],
            (new LexicalGrammar($faker, $version, true))->tokenize($result),
        );
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testUpdateJoinDerivedStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->updateJoinDerivedStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))->tokenize($sql);
        $joinTokens = array_intersect($tokens, ['JOIN_SYM', 'STRAIGHT_JOIN']);
        $join = array_key_first($joinTokens);
        $select = array_search('SELECT_SYM', $tokens, true);

        $faker->seed($seed);
        self::assertSame($sql, $provider->updateJoinDerivedStatement(40));
        self::assertSame('UPDATE_SYM', $tokens[0]);
        self::assertIsInt($join);
        self::assertIsInt($select);
        self::assertGreaterThan($join, $select);
        self::assertContains('FROM', $tokens);
        self::assertContains('GROUP_SYM', $tokens);
        self::assertContains('ON_SYM', $tokens);
        self::assertContains('SET_SYM', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testInsertSelectCompoundStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->insertSelectCompoundStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->insertSelectCompoundStatement(40));
        self::assertSame('INSERT_SYM', $tokens[0]);
        self::assertContains('UNION_SYM', $tokens);
        self::assertContains('ALL', $tokens);
        self::assertGreaterThanOrEqual(
            2,
            count(array_filter($tokens, static fn (string $token): bool => $token === 'SELECT_SYM')),
        );
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testInsertRowAliasUpsertStatement(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed($seed);
        $sql = $provider->insertRowAliasUpsertStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'mysql-8.4.7', true))->tokenize($sql);

        $faker->seed($seed);
        self::assertSame($sql, $provider->insertRowAliasUpsertStatement(40));
        self::assertSame('INSERT_SYM', $tokens[0]);
        self::assertContains('VALUES', $tokens);
        self::assertContains('AS', $tokens);
        self::assertContains('DUPLICATE_SYM', $tokens);
        self::assertContains('UPDATE_SYM', $tokens);
    }

    public function testSelectStatementMinimalDepthProducesOutput(): void
    {
        $faker = Factory::create();
        $provider = new MySqlStatementProvider($faker);
        $faker->seed(12345);

        $result = $provider->selectStatement(maxDepth: 1);

        self::assertMatchesRegularExpression('/\bSELECT\b/i', $result);
    }

    #[DataProvider('providerMultipleGenerationSeeds')]
    public function testMultipleGenerationsReturnDifferentResults(int $seed1, int $seed2): void
    {
        $faker1 = Factory::create();
        $faker1->seed($seed1);
        $provider1 = new MySqlStatementProvider($faker1);

        $faker2 = Factory::create();
        $faker2->seed($seed2);
        $provider2 = new MySqlStatementProvider($faker2);

        self::assertNotSame($provider1->selectStatement(maxDepth: 3), $provider2->selectStatement(maxDepth: 3));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerMultiTableGenerationSeed(): iterable
    {
        foreach (range(0, 31) as $seed) {
            yield "seed {$seed}" => [$seed];
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function providerMultipleGenerationSeeds(): iterable
    {
        yield 'seeds 0 and 1' => [0, 1];
        yield 'seeds 5 and 10' => [5, 10];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMySqlVersion(): iterable
    {
        foreach (SqlVersion::names('mysql') as $version) {
            yield $version => [$version];
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerTargetedGenerationSeed(): iterable
    {
        foreach (range(0, 15) as $seed) {
            yield "seed {$seed}" => [$seed];
        }
    }

}
