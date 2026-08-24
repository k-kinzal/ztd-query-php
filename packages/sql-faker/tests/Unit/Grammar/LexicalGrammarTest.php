<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSql\StatementType as PostgreSqlStatementType;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

#[CoversNothing]
#[Medium]
final class LexicalGrammarTest extends TestCase
{
    #[DataProvider('providerSupportedMySqlVersion')]
    public function testSupportedMySqlVersionBindsGrammarAndLexerProfileTogether(string $version): void
    {
        $faker = Factory::create();
        $faker->seed(20260814);
        $provider = new MySqlProvider($faker, $version);
        $statements = array_map(static fn (int $iteration): string => $provider->sql(maxDepth: 20), range(1, 20));

        self::assertNotContains('', $statements, $version);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSupportedMySqlVersion(): iterable
    {
        yield 'MySQL 5.6' => ['mysql-5.6.51'];
        yield 'MySQL 5.7' => ['mysql-5.7.44'];
        yield 'MySQL 8.0' => ['mysql-8.0.44'];
        yield 'MySQL 8.1' => ['mysql-8.1.0'];
        yield 'MySQL 8.2' => ['mysql-8.2.0'];
        yield 'MySQL 8.3' => ['mysql-8.3.0'];
        yield 'MySQL 8.4' => ['mysql-8.4.7'];
        yield 'MySQL 9.0' => ['mysql-9.0.1'];
        yield 'MySQL 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerPostgreSqlStatementType')]
    public function testSupportedPostgreSqlVersionBindsGrammarAndLexerProfileTogether(PostgreSqlStatementType $type): void
    {
        $faker = Factory::create();
        $faker->seed(20260814);
        $provider = new PostgreSqlProvider($faker, 'pg-17.2');

        self::assertNotSame('', $provider->sql($type, maxDepth: 20), $type->name);
    }

    /**
     * @return iterable<string, array{PostgreSqlStatementType}>
     */
    public static function providerPostgreSqlStatementType(): iterable
    {
        foreach (PostgreSqlStatementType::cases() as $type) {
            yield $type->name => [$type];
        }
    }

    public function testSupportedSqliteVersionBindsGrammarAndLexerProfileTogether(): void
    {
        $faker = Factory::create();
        $faker->seed(20260814);
        $provider = new SqliteProvider($faker, 'sqlite-3.47.2');
        $statements = array_map(static fn (int $iteration): string => $provider->sql(maxDepth: 20), range(1, 20));

        self::assertNotContains('', $statements);
    }
}
