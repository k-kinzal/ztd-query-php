<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use LogicException;
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
use SqlFaker\Contract\SupportedLanguage as SupportedLanguageContract;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\MySql\SqlGenerator as MySqlSqlGenerator;
use SqlFaker\MySql\SupportedLanguage as MySqlSupportedLanguage;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSql\SqlGenerator as PostgreSqlSqlGenerator;
use SqlFaker\PostgreSql\SupportedLanguage as PostgreSqlSupportedLanguage;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\Sqlite\SqlGenerator as SqliteSqlGenerator;
use SqlFaker\Sqlite\SupportedLanguage as SqliteSupportedLanguage;
use SqlFaker\SqliteProvider;

#[CoversClass(MySqlSupportedLanguage::class)]
#[CoversClass(PostgreSqlSupportedLanguage::class)]
#[CoversClass(SqliteSupportedLanguage::class)]
#[UsesClass(FamilyDefinition::class)]
#[UsesClass(FamilyRequest::class)]
#[UsesClass(GrammarAlternativeSnapshot::class)]
#[UsesClass(GrammarRuleSnapshot::class)]
#[UsesClass(GrammarSnapshot::class)]
#[UsesClass(GrammarSnapshotBuilder::class)]
#[UsesClass(GrammarSymbolSnapshot::class)]
#[UsesClass(SqlWitness::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(MySqlSqlGenerator::class)]
#[UsesClass(MySqlProvider::class)]
#[UsesClass(PostgreSqlSqlGenerator::class)]
#[UsesClass(PostgreSqlProvider::class)]
#[UsesClass(SqliteSqlGenerator::class)]
#[UsesClass(SqliteProvider::class)]
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

    public function testMySqlSupportedLanguageSnapshotFingerprintIsStable(): void
    {
        self::assertSame(
            'be2ebf7b66a7337598127a81b392760ede6d876850feea6a74a8eab9c2832097',
            self::contractFingerprint(new MySqlSupportedLanguage('mysql-8.0.44')),
        );
    }

    public function testMySqlSupportedLanguageGeneratesWitnessesForEveryFamily(): void
    {
        $this->assertGeneratesWitnessesForEveryFamily(new MySqlSupportedLanguage('mysql-8.0.44'));
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

    public function testPostgreSqlSupportedLanguageSnapshotFingerprintIsStable(): void
    {
        self::assertSame(
            'b226440418850a9a412d8a7c0e0b74004305d14bb5e981e05ac8f62e3154b264',
            self::contractFingerprint(new PostgreSqlSupportedLanguage()),
        );
    }

    public function testPostgreSqlSupportedLanguageGeneratesWitnessesForEveryFamily(): void
    {
        $this->assertGeneratesWitnessesForEveryFamily(new PostgreSqlSupportedLanguage());
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

    public function testSqliteSupportedLanguageSnapshotFingerprintIsStable(): void
    {
        self::assertSame(
            '38d44310096587006d846b11cb076c2205810fd5a036ea61b416719aba064b36',
            self::contractFingerprint(new SqliteSupportedLanguage()),
        );
    }

    public function testSqliteSupportedLanguageGeneratesWitnessesForEveryFamily(): void
    {
        $this->assertGeneratesWitnessesForEveryFamily(new SqliteSupportedLanguage());
    }

    private function assertGeneratesWitnessesForEveryFamily(SupportedLanguageContract $language): void
    {
        foreach ($language->familyCatalog() as $family) {
            foreach (self::parameterSetsFor($family) as $parameters) {
                $witness = $language->generateWitness(new FamilyRequest($family->id, $parameters));

                self::assertSame($family->id, $witness->familyId, $family->id);
                self::assertSame($parameters, $witness->parameters, $family->id);
                self::assertNotSame('', $witness->sql, $family->id);

                foreach ($family->propertyNames as $propertyName) {
                    self::assertArrayHasKey($propertyName, $witness->properties, $family->id . ':' . $propertyName);
                }

                if (array_key_exists('arity', $parameters)) {
                    foreach ($family->propertyNames as $propertyName) {
                        if (str_contains($propertyName, 'arity')) {
                            self::assertSame($parameters['arity'], $witness->properties[$propertyName], $family->id . ':' . $propertyName);
                        }
                    }
                }

                if (array_key_exists('schema_qualified', $parameters) && in_array('schema_qualified', $family->propertyNames, true)) {
                    self::assertSame($parameters['schema_qualified'], $witness->properties['schema_qualified'], $family->id);
                }
            }
        }
    }

    private static function contractFingerprint(SupportedLanguageContract $language): string
    {
        $snapshot = $language->grammarSnapshot();
        $rules = [];
        foreach ($snapshot->rules as $ruleName => $rule) {
            $rules[$ruleName] = array_map(
                static fn (GrammarAlternativeSnapshot $alternative): array => $alternative->sequence(),
                $rule->alternatives,
            );
        }
        ksort($rules);

        $familyAnchors = $snapshot->familyAnchors;
        ksort($familyAnchors);

        $catalog = array_map(
            static fn (FamilyDefinition $family): array => [
                'id' => $family->id,
                'anchors' => $family->anchorRules,
                'params' => $family->parameterNames,
                'props' => $family->propertyNames,
            ],
            $language->familyCatalog(),
        );
        usort(
            $catalog,
            static fn (array $left, array $right): int => $left['id'] <=> $right['id'],
        );

        $payload = [
            'dialect' => $snapshot->dialect,
            'startRule' => $snapshot->startRule,
            'entryRules' => $snapshot->entryRules,
            'familyAnchors' => $familyAnchors,
            'rules' => $rules,
            'catalog' => $catalog,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array<string, bool|int>>
     */
    private static function parameterSetsFor(FamilyDefinition $family): array
    {
        $parameterSets = [[]];

        foreach ($family->parameterNames as $parameterName) {
            $values = match ($parameterName) {
                'arity' => [1, 8],
                'schema_qualified' => [true, false],
                default => throw new LogicException(sprintf('Unhandled family parameter: %s', $parameterName)),
            };

            $expanded = [];
            foreach ($parameterSets as $parameterSet) {
                foreach ($values as $value) {
                    $next = $parameterSet;
                    $next[$parameterName] = $value;
                    $expanded[] = $next;
                }
            }

            $parameterSets = $expanded;
        }

        return $parameterSets;
    }
}
