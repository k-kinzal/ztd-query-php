<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

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
use SqlFaker\Sqlite\SqlGenerator;
use SqlFaker\Sqlite\SupportedLanguage;
use SqlFaker\SqliteProvider;

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
#[UsesClass(SqliteProvider::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testExposesSqliteSupportedLanguageContract(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest(
            'sqlite.constraint.select.values_clause',
            ['arity' => 3],
        ));

        self::assertSame('sqlite', $language->dialect());
        self::assertContains('cmd', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['attach_stmt', 'safe_attach_filename_expr', 'safe_attach_schema_expr'],
            $language->family('sqlite.constraint.attach.expression')->anchorRules,
        );
        self::assertSame(3, $witness->properties['row_arity']);
        self::assertStringStartsWith('VALUES(', $witness->sql);
    }

    public function testGeneratesStarRequiresFromWitness(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest('sqlite.constraint.select.star_requires_from'));

        self::assertStringStartsWith('SELECT ', $witness->sql);
    }

    public function testGeneratesTemporaryObjectNameBindingWitness(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest('sqlite.constraint.temporary_object_name_binding'));

        self::assertMatchesRegularExpression(
            '/^CREATE\s+TEMP(?:ORARY)?\s+(?:TABLE|VIEW|TRIGGER)\s+(?:IF\s+NOT\s+EXISTS\s+)?[^\s.(]+/',
            $witness->sql,
        );
    }
}
