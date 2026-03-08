<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyDefinition;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\Contract\GrammarAlternativeSnapshot;
use SqlFaker\Contract\GrammarRuleSnapshot;
use SqlFaker\Contract\GrammarSnapshot;
use SqlFaker\Contract\GrammarSnapshotBuilder;
use SqlFaker\Contract\GrammarSymbolSnapshot;
use SqlFaker\Contract\SqlWitness;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSql\SupportedLanguage;
use SqlFaker\PostgreSqlProvider;

#[CoversClass(SupportedLanguage::class)]
#[UsesClass(FamilyDefinition::class)]
#[UsesClass(FamilyRequest::class)]
#[UsesClass(GrammarAlternativeSnapshot::class)]
#[UsesClass(GrammarRuleSnapshot::class)]
#[UsesClass(GrammarSnapshot::class)]
#[UsesClass(GrammarSnapshotBuilder::class)]
#[UsesClass(GrammarSymbolSnapshot::class)]
#[UsesClass(SqlWitness::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(SqlGenerator::class)]
#[UsesClass(PostgreSqlProvider::class)]
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
