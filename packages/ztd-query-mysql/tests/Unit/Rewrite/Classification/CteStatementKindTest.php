<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Classification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\Classification\CteStatementKind;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(CteStatementKind::class)]
final class CteStatementKindTest extends TestCase
{
    public function testClassifyWithFallbackCase1(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'SELECT';
        $expected = \ZtdQuery\Rewrite\QueryKind::READ;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase2(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'UPDATE';
        $expected = \ZtdQuery\Rewrite\QueryKind::WRITE_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase3(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'DELETE';
        $expected = \ZtdQuery\Rewrite\QueryKind::WRITE_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase4(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'INSERT';
        $expected = \ZtdQuery\Rewrite\QueryKind::WRITE_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase5(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'REPLACE';
        $expected = \ZtdQuery\Rewrite\QueryKind::WRITE_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase6(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'TRUNCATE';
        $expected = \ZtdQuery\Rewrite\QueryKind::WRITE_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase7(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'CREATE';
        $expected = \ZtdQuery\Rewrite\QueryKind::DDL_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase8(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'DROP';
        $expected = \ZtdQuery\Rewrite\QueryKind::DDL_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

    public function testClassifyWithFallbackCase9(): void
    {
        $classifier = new CteStatementKind();
        $keyword = 'ALTER';
        $expected = \ZtdQuery\Rewrite\QueryKind::DDL_SIMULATED;
        self::assertSame($expected, $classifier->classifyWithFallback('WITH c AS (SELECT "DELETE") /* UPDATE */ ' . $keyword . ' t'));
        self::assertNull($classifier->classifyWithFallback('WITH c AS (SELECT 1)'));
        self::assertNull($classifier->classifyWithFallback('SELECT 1'));
    }

}
