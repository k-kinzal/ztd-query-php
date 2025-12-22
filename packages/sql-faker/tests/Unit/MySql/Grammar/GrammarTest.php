<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\Grammar\Grammar;

final class GrammarTest extends TestCase
{
    public function testLoad(): void
    {
        $grammar = Grammar::load();

        self::assertNotEmpty($grammar->startSymbol);
        self::assertNotEmpty($grammar->ruleMap);
    }

    public function testLoadWithDefaultVersion(): void
    {
        $grammar = Grammar::load(null);

        self::assertNotEmpty($grammar->startSymbol);
    }

    public function testLoadWithExplicitVersion(): void
    {
        $grammar = Grammar::load('mysql-8.4.7');

        self::assertNotEmpty($grammar->ruleMap);
    }

    public function testLoadWithNonExistentVersionThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grammar file not found');

        Grammar::load('non-existent-version');
    }

    public function testLoadedGrammarHasExpectedStructure(): void
    {
        $grammar = Grammar::load();

        // MySQL grammar should have common SQL rules
        self::assertArrayHasKey('select_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('insert_stmt', $grammar->ruleMap);

        // Check rule structure
        $selectRule = $grammar->ruleMap['select_stmt'];
        self::assertSame('select_stmt', $selectRule->lhs);
        self::assertNotEmpty($selectRule->alternatives);
    }
}
