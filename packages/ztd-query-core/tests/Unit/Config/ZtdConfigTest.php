<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ZtdQuery\Config\SqlBehaviorRule;

#[UsesClass(SqlBehaviorRule::class)]
#[CoversClass(ZtdConfig::class)]
final class ZtdConfigTest extends TestCase
{
    public function testDefaultCreatesConfigWithExceptionBehavior(): void
    {
        $config = ZtdConfig::default();

        self::assertSame(UnsupportedSqlBehavior::Exception, $config->unsupportedBehavior());
        self::assertSame(UnknownSchemaBehavior::Passthrough, $config->unknownSchemaBehavior());
        self::assertSame([], $config->behaviorRules());
    }

    public function testUnsupportedBehaviorReturnsConfiguredValue(): void
    {
        $config = new ZtdConfig(UnsupportedSqlBehavior::Ignore);

        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->unsupportedBehavior());
    }

    public function testUnknownSchemaBehaviorReturnsConfiguredValue(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Exception
        );

        self::assertSame(UnknownSchemaBehavior::Exception, $config->unknownSchemaBehavior());
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

        self::assertCount(2, $rules);
        self::assertSame('SET', $rules[0]->pattern());
        self::assertSame(UnsupportedSqlBehavior::Ignore, $rules[0]->behavior());
        self::assertSame('/^SHOW\s+/i', $rules[1]->pattern());
        self::assertSame(UnsupportedSqlBehavior::Notice, $rules[1]->behavior());
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

        self::assertSame(UnsupportedSqlBehavior::Notice, $config->getBehaviorFor('SET foo = 1'));
        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('BEGIN'));
    }

    public function testGetBehaviorForReturnsNullWhenNoMatch(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            ['SET' => UnsupportedSqlBehavior::Ignore]
        );

        self::assertNull($config->getBehaviorFor('DROP TABLE users'));
    }

    public function testResolveUnsupportedBehaviorUsesMatchingRule(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            ['SET' => UnsupportedSqlBehavior::Ignore]
        );

        self::assertSame(
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

        self::assertSame(
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

        self::assertSame(
            UnsupportedSqlBehavior::Ignore,
            $config->getBehaviorFor('SET SESSION foo = 1')
        );

        self::assertSame(
            UnsupportedSqlBehavior::Notice,
            $config->getBehaviorFor('SET foo = 1')
        );
    }

    public function testPrefixMatchingBehavior(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                'SHOW' => UnsupportedSqlBehavior::Ignore,
                'SET SESSION' => UnsupportedSqlBehavior::Notice,
            ]
        );

        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('SHOW TABLES'));
        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('show tables'));
        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('  SHOW TABLES'));
        self::assertSame(UnsupportedSqlBehavior::Notice, $config->getBehaviorFor('SET SESSION foo = 1'));
        self::assertNull($config->getBehaviorFor('SET foo = 1'));
        self::assertNull($config->getBehaviorFor('SELECT * FROM users'));
    }

    public function testRegexMatchingBehavior(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Exception,
            UnknownSchemaBehavior::Passthrough,
            [
                '/^SHOW\s+VARIABLES/i' => UnsupportedSqlBehavior::Ignore,
                '/@@\w+/' => UnsupportedSqlBehavior::Notice,
            ]
        );

        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('SHOW VARIABLES'));
        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('SHOW  VARIABLES'));
        self::assertSame(UnsupportedSqlBehavior::Notice, $config->getBehaviorFor('SELECT @@version'));
        self::assertNull($config->getBehaviorFor('SELECT version()'));
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

        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('BEGIN'));
        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('COMMIT'));
        self::assertSame(UnsupportedSqlBehavior::Ignore, $config->getBehaviorFor('ROLLBACK TO SAVEPOINT sp1'));
        self::assertSame(UnsupportedSqlBehavior::Notice, $config->getBehaviorFor('SET NAMES utf8mb4'));
        self::assertNull($config->getBehaviorFor('DROP TABLE users'));
    }

    public function testEmptyBehaviorRules(): void
    {
        $config = new ZtdConfig(
            UnsupportedSqlBehavior::Notice,
            UnknownSchemaBehavior::Passthrough,
            []
        );

        self::assertNull($config->getBehaviorFor('SET foo = 1'));
        self::assertSame(
            UnsupportedSqlBehavior::Notice,
            $config->resolveUnsupportedBehavior('SET foo = 1')
        );
    }
}
