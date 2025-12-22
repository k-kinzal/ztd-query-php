<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

final class ZtdConfigTest extends TestCase
{
    public function testDefaultCreatesConfigWithExceptionBehavior(): void
    {
        $config = ZtdConfig::default();

        $this->assertSame(UnsupportedSqlBehavior::Exception, $config->unsupportedBehavior());
        $this->assertSame(UnknownSchemaBehavior::Passthrough, $config->unknownSchemaBehavior());
        $this->assertSame([], $config->behaviorRules());
    }

    public function testUnsupportedBehaviorReturnsConfiguredValue(): void
    {
        $config = new ZtdConfig(UnsupportedSqlBehavior::Ignore);

        $this->assertSame(UnsupportedSqlBehavior::Ignore, $config->unsupportedBehavior());
    }

    public function testUnknownSchemaBehaviorReturnsConfiguredValue(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Exception
        );

        $this->assertSame(UnknownSchemaBehavior::Exception, $config->unknownSchemaBehavior());
    }

    public function testBehaviorRulesReturnsConfiguredRules(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                'SET' => UnsupportedSqlBehavior::Ignore,
                '/^SHOW\s+/i' => UnsupportedSqlBehavior::Notice,
            ]
        );

        $rules = $config->behaviorRules();

        $this->assertCount(2, $rules);
        $this->assertSame('SET', $rules[0]->pattern());
        $this->assertSame(UnsupportedSqlBehavior::Ignore, $rules[0]->behavior());
        $this->assertSame('/^SHOW\s+/i', $rules[1]->pattern());
        $this->assertSame(UnsupportedSqlBehavior::Notice, $rules[1]->behavior());
    }

    public function testGetBehaviorForReturnsMatchingRuleBehavior(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                '/^SET\s+/i' => UnsupportedSqlBehavior::Notice,
                'BEGIN' => UnsupportedSqlBehavior::Ignore,
            ]
        );

        $this->assertSame(UnsupportedSqlBehavior::Notice, $config->getBehaviorFor('SET foo = 1'));
        $this->assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('BEGIN'));
    }

    public function testGetBehaviorForReturnsNullWhenNoMatch(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            ['SET' => UnsupportedSqlBehavior::Ignore]
        );

        $this->assertNull($config->getBehaviorFor('DROP TABLE users'));
    }

    public function testResolveUnsupportedBehaviorUsesMatchingRule(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            ['SET' => UnsupportedSqlBehavior::Ignore]
        );

        $this->assertSame(
            UnsupportedSqlBehavior::Ignore,
            $config->resolveUnsupportedBehavior('SET foo = 1')
        );
    }

    public function testResolveUnsupportedBehaviorUsesDefaultWhenNoMatch(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            ['SET' => UnsupportedSqlBehavior::Ignore]
        );

        $this->assertSame(
            UnsupportedSqlBehavior::Exception,
            $config->resolveUnsupportedBehavior('DROP TABLE users')
        );
    }

    public function testFirstMatchWins(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                '/^SET\s+SESSION/i' => UnsupportedSqlBehavior::Ignore,
                '/^SET\s+/i' => UnsupportedSqlBehavior::Notice,
            ]
        );

        // More specific pattern should win (it's first)
        $this->assertSame(
            UnsupportedSqlBehavior::Ignore,
            $config->getBehaviorFor('SET SESSION foo = 1')
        );

        // General pattern for non-SESSION SET
        $this->assertSame(
            UnsupportedSqlBehavior::Notice,
            $config->getBehaviorFor('SET foo = 1')
        );
    }

    #[DataProvider('providerPrefixMatching')]
    public function testPrefixMatchingBehavior(string $sql, ?UnsupportedSqlBehavior $expected): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                'SHOW' => UnsupportedSqlBehavior::Ignore,
                'SET SESSION' => UnsupportedSqlBehavior::Notice,
            ]
        );

        $this->assertSame($expected, $config->getBehaviorFor($sql));
    }

    /**
     * @return array<string, array{string, UnsupportedSqlBehavior|null}>
     */
    public static function providerPrefixMatching(): array
    {
        return [
            'SHOW TABLES matches' => ['SHOW TABLES', UnsupportedSqlBehavior::Ignore],
            'show tables lowercase matches' => ['show tables', UnsupportedSqlBehavior::Ignore],
            'SHOW with whitespace matches' => ['  SHOW TABLES', UnsupportedSqlBehavior::Ignore],
            'SET SESSION matches' => ['SET SESSION foo = 1', UnsupportedSqlBehavior::Notice],
            'SET without SESSION does not match' => ['SET foo = 1', null],
            'SELECT does not match' => ['SELECT * FROM users', null],
        ];
    }

    #[DataProvider('providerRegexMatching')]
    public function testRegexMatchingBehavior(string $sql, ?UnsupportedSqlBehavior $expected): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                '/^SHOW\s+VARIABLES/i' => UnsupportedSqlBehavior::Ignore,
                '/@@\w+/' => UnsupportedSqlBehavior::Notice,
            ]
        );

        $this->assertSame($expected, $config->getBehaviorFor($sql));
    }

    /**
     * @return array<string, array{string, UnsupportedSqlBehavior|null}>
     */
    public static function providerRegexMatching(): array
    {
        return [
            'SHOW VARIABLES matches' => ['SHOW VARIABLES', UnsupportedSqlBehavior::Ignore],
            'SHOW  VARIABLES with extra space matches' => ['SHOW  VARIABLES', UnsupportedSqlBehavior::Ignore],
            'SELECT with @@ matches' => ['SELECT @@version', UnsupportedSqlBehavior::Notice],
            'SELECT without @@ does not match' => ['SELECT version()', null],
        ];
    }

    public function testMixedPrefixAndRegexRules(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                'BEGIN' => UnsupportedSqlBehavior::Ignore,
                '/^COMMIT\b/i' => UnsupportedSqlBehavior::Ignore,
                'ROLLBACK' => UnsupportedSqlBehavior::Ignore,
                '/^SET\s+/i' => UnsupportedSqlBehavior::Notice,
            ]
        );

        $this->assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('BEGIN'));
        $this->assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('COMMIT'));
        $this->assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('ROLLBACK TO SAVEPOINT sp1'));
        $this->assertSame(UnsupportedSqlBehavior::Notice, $config->getBehaviorFor('SET NAMES utf8mb4'));
        $this->assertNull($config->getBehaviorFor('DROP TABLE users'));
    }

    public function testEmptyBehaviorRules(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Notice,
            UnknownSchemaBehavior::Passthrough,
            []
        );

        $this->assertNull($config->getBehaviorFor('SET foo = 1'));
        $this->assertSame(
            UnsupportedSqlBehavior::Notice,
            $config->resolveUnsupportedBehavior('SET foo = 1')
        );
    }
}
