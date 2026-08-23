<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ZtdQuery\PhpStanCustomRules\Rule\SqlStatementTemplateDetector;

#[CoversClass(SqlStatementTemplateDetector::class)]
#[Medium]
final class SqlStatementTemplateDetectorTest extends TestCase
{
    public function testDetectsStatementsAndExecutableFragments(): void
    {
        self::assertTrue(SqlStatementTemplateDetector::contains('SELECT id, name FROM users'));
        self::assertTrue(SqlStatementTemplateDetector::contains('SELECT DISTINCT COUNT(*) FROM users'));
        self::assertTrue(SqlStatementTemplateDetector::contains('WITH RECURSIVE t(n) AS (SELECT n FROM q) SELECT n FROM t'));
        self::assertTrue(SqlStatementTemplateDetector::contains('INSERT INTO users (id) VALUES (1)'));
        self::assertTrue(SqlStatementTemplateDetector::contains('UPDATE users SET name = 1'));
        self::assertTrue(SqlStatementTemplateDetector::contains('DELETE FROM users'));
        self::assertTrue(SqlStatementTemplateDetector::contains('CREATE TABLE users (id INT)'));
        self::assertTrue(SqlStatementTemplateDetector::contains('START TRANSACTION'));
        self::assertTrue(SqlStatementTemplateDetector::contains('SET autocommit = 0'));
        self::assertTrue(SqlStatementTemplateDetector::contains('/* fixture */ SELECT id FROM users'));
        self::assertTrue(SqlStatementTemplateDetector::contains("-- fixture\nSELECT id FROM users"));
        self::assertTrue(SqlStatementTemplateDetector::contains('value = (SELECT id FROM users)'));
        self::assertTrue(SqlStatementTemplateDetector::contains(' FROM users WHERE id = 1'));
        self::assertTrue(SqlStatementTemplateDetector::contains('COMMIT', true));
    }

    public function testAllowsGrammarSymbolsAndDiagnostics(): void
    {
        self::assertFalse(SqlStatementTemplateDetector::contains('SELECT'));
        self::assertFalse(SqlStatementTemplateDetector::contains('FROM'));
        self::assertFalse(SqlStatementTemplateDetector::contains('WHERE'));
        self::assertFalse(SqlStatementTemplateDetector::contains('COMMIT'));
        self::assertFalse(SqlStatementTemplateDetector::contains('WITH ROLLUP'));
        self::assertFalse(SqlStatementTemplateDetector::contains('Cannot select a lexical profile from the catalog.'));
        self::assertFalse(SqlStatementTemplateDetector::contains('select_stmt'));
    }
}
