<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Grammar;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFaker\Grammar\SqlVersion;

#[CoversClass(PgGrammar::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
#[UsesClass(SqlVersion::class)]
final class PgGrammarTest extends TestCase
{
    public function testLoad(): void
    {
        $grammar = PgGrammar::load();

        self::assertInstanceOf(Grammar::class, $grammar);
    }

    public function testLoadWithExplicitVersion(): void
    {
        $grammar = PgGrammar::load('pg-17.2');

        self::assertInstanceOf(Grammar::class, $grammar);
    }

    public function testResolveVersionUsesExactConfiguredDefault(): void
    {
        self::assertSame('pg-17.2', PgGrammar::resolveVersion());
        self::assertSame('pg-17.2', PgGrammar::resolveVersion('pg-17.2'));
    }

    public function testLoadWithNonExistentVersionThrows(): void
    {
        $this->expectException(RuntimeException::class);

        PgGrammar::load('pg-999.999');
    }

    public function testLoadedGrammarStructure(): void
    {
        $grammar = PgGrammar::load();

        self::assertArrayHasKey('stmtmulti', $grammar->ruleMap);
        self::assertArrayHasKey('SelectStmt', $grammar->ruleMap);
        self::assertArrayHasKey('InsertStmt', $grammar->ruleMap);
        self::assertArrayHasKey('UpdateStmt', $grammar->ruleMap);
        self::assertArrayHasKey('DeleteStmt', $grammar->ruleMap);
    }
}
