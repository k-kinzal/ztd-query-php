<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySql\SupportedLanguage;
use SqlFaker\MySqlProvider;

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
#[UsesClass(MySqlProvider::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testExposesMySqlSupportedLanguageContract(): void
    {
        $language = new SupportedLanguage('mysql-8.0.44');
        $witness = $language->generateWitness(new FamilyRequest(
            'mysql.constraint.table_value_constructor',
            ['arity' => 3],
        ));

        self::assertSame('mysql', $language->dialect());
        self::assertSame(['simple_statement_or_begin'], $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['simple_statement_or_begin'],
            $language->family('mysql.statement.any')->anchorRules,
        );
        self::assertSame(3, $witness->properties['row_arity']);
        self::assertStringStartsWith('VALUES ROW(', $witness->sql);
    }

    /**
     * @param list<string> $expectedAnchorRules
     */
    #[DataProvider('providerChangeReplicationSourceVersion')]
    public function testGeneratesChangeReplicationSourceWitnessForSupportedVersions(
        string $version,
        array $expectedAnchorRules,
    ): void {
        $language = new SupportedLanguage($version);
        $witness = $language->generateWitness(new FamilyRequest('mysql.constraint.change_replication_source'));

        self::assertSame(
            $expectedAnchorRules,
            $language->family('mysql.constraint.change_replication_source')->anchorRules,
        );
        self::assertStringStartsWith('CHANGE REPLICATION SOURCE TO ', $witness->sql);
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function providerChangeReplicationSourceVersion(): iterable
    {
        yield 'mysql 8.0 source alias' => ['mysql-8.0.44', ['change']];
        yield 'mysql 8.4 dedicated statement' => ['mysql-8.4.7', ['change_replication_stmt']];
    }
}
