<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\MySql\SupportedLanguage as MySqlSupportedLanguage;
use SqlFaker\PostgreSql\SupportedLanguage as PostgreSqlSupportedLanguage;
use SqlFaker\Sqlite\SupportedLanguage as SqliteSupportedLanguage;

#[CoversClass(MySqlSupportedLanguage::class)]
#[CoversClass(PostgreSqlSupportedLanguage::class)]
#[CoversClass(SqliteSupportedLanguage::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testMySqlSupportedLanguageExposesPublicContract(): void
    {
        $language = new MySqlSupportedLanguage('mysql-8.0.44');

        self::assertSame('mysql', $language->dialect());
        self::assertNotSame('', $language->grammarSnapshot()->startRule);
        self::assertSame(['simple_statement_or_begin'], $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['simple_statement_or_begin'],
            $language->family('mysql.statement.any')->anchorRules,
        );
        self::assertNotSame(
            '',
            $language->generateWitness(new FamilyRequest('mysql.statement.select'))->sql,
        );
    }

    public function testPostgreSqlSupportedLanguageExposesPublicContract(): void
    {
        $language = new PostgreSqlSupportedLanguage();

        self::assertSame('postgresql', $language->dialect());
        self::assertNotSame('', $language->grammarSnapshot()->startRule);
        self::assertContains('stmtmulti', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['SelectStmt', 'distinct_clause', 'safe_distinct_on_expr_list'],
            $language->family('postgresql.constraint.distinct_on')->anchorRules,
        );
        self::assertNotSame(
            '',
            $language->generateWitness(new FamilyRequest('postgresql.statement.select'))->sql,
        );
    }

    public function testSqliteSupportedLanguageExposesPublicContract(): void
    {
        $language = new SqliteSupportedLanguage();

        self::assertSame('sqlite', $language->dialect());
        self::assertNotSame('', $language->grammarSnapshot()->startRule);
        self::assertContains('cmd', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['attach_stmt', 'safe_attach_filename_expr', 'safe_attach_schema_expr'],
            $language->family('sqlite.constraint.attach.expression')->anchorRules,
        );
        self::assertNotSame(
            '',
            $language->generateWitness(new FamilyRequest('sqlite.statement.select'))->sql,
        );
    }
}
