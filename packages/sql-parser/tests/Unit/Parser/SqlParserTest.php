<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\SqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

#[CoversNothing]
final class SqlParserTest extends TestCase
{
    /**
     * @return iterable<string, array{SqlParser}>
     */
    public static function providerParsers(): iterable
    {
        yield 'mysql' => [new MySqlParser()];
        yield 'postgres' => [new PostgreSqlParser()];
        yield 'sqlite' => [new SqliteParser()];
    }

    #[DataProvider('providerParsers')]
    public function testParsePreservesSourceThroughTheContract(SqlParser $parser): void
    {
        $sql = ' SELECT 1 /* trailing */ ';
        self::assertSame($sql, $parser->parse($sql)->toString());
    }

    #[DataProvider('providerParsers')]
    public function testTokenizePreservesSourceThroughTheContract(SqlParser $parser): void
    {
        $sql = ' SELECT 1 /* trailing */ ';
        $restored = implode('', array_map(static fn (Token $token): string => $token->leading . $token->text, $parser->tokenize($sql)));
        self::assertSame($sql, $restored);
    }

    #[DataProvider('providerParsers')]
    public function testVersionIdentifiesTheConfiguredRelease(SqlParser $parser): void
    {
        self::assertNotSame('', $parser->version());
    }
}
