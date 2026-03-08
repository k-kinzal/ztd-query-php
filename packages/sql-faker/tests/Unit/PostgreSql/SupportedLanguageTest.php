<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\PostgreSql\SupportedLanguage;

#[CoversClass(SupportedLanguage::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testExposesPostgreSqlSupportedLanguageContract(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest('postgresql.statement.select'));

        self::assertSame('postgresql', $language->dialect());
        self::assertContains('stmtmulti', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['SelectStmt', 'distinct_clause', 'safe_distinct_on_expr_list'],
            $language->family('postgresql.constraint.distinct_on')->anchorRules,
        );
        self::assertNotSame('', $witness->sql);
    }
}
