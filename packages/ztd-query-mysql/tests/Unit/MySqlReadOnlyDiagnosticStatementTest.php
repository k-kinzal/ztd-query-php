<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlReadOnlyDiagnosticStatement;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlReadOnlyDiagnosticStatement::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlReadOnlyDiagnosticStatementTest extends TestCase
{
    #[DataProvider('providerStatement')]
    public function testIsSafeClassifiesOnlySafeMySqlDiagnostics(string $sql, bool $expected): void
    {
        self::assertSame($expected, MySqlReadOnlyDiagnosticStatement::isSafe($sql));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerStatement(): iterable
    {
        yield 'explain select' => ['EXPLAIN SELECT * FROM users', true];
        yield 'explain non-executing update' => ['EXPLAIN UPDATE users SET active = FALSE', true];
        yield 'explain analyze select' => ['EXPLAIN ANALYZE SELECT * FROM users', true];
        yield 'executing update' => ['EXPLAIN ANALYZE UPDATE users SET active = FALSE', false];
        yield 'show' => ['SHOW CREATE TABLE users', true];
        yield 'describe' => ['DESCRIBE users', true];
        yield 'postgres spelling is not execution' => ['EXPLAIN ANALYSE UPDATE users SET active = FALSE', true];
        yield 'multiple statements' => ['SHOW TABLES; DELETE FROM users', false];
        yield 'ordinary query' => ['SELECT * FROM users', false];
        yield 'empty explain' => ['EXPLAIN', false];
    }
    public function testContainsKeywordReportsAKeywordWrittenAmongTheTokens(): void
    {
        $tokens = SqlTokenStream::tokenize('SHOW TABLES', MySqlLexerProfile::create())->significantTokens();

        self::assertTrue(MySqlReadOnlyDiagnosticStatement::containsKeyword($tokens, ['TABLES']));
    }

    public function testContainsKeywordIsFalseWhereNoneOfThemIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('SHOW TABLES', MySqlLexerProfile::create())->significantTokens();

        self::assertFalse(MySqlReadOnlyDiagnosticStatement::containsKeyword($tokens, ['UPDATE', 'DELETE']));
    }

}
