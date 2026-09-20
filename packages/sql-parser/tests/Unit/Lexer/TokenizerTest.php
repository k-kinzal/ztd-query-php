<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Lexer\Tokenizer;
use SqlParser\MySql\MySqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Tokenizer::class)]
#[UsesClass(Token::class)]
#[Small]
final class TokenizerTest extends TestCase
{
    #[DataProvider('providerTokenizers')]
    public function testTokenizeReadsTextIntoTerminalsEndingWithTheEndMarker(Tokenizer $tokenizer): void
    {
        $tokens = $tokenizer->tokenize('SELECT 1');
        $texts = array_values(array_filter(
            array_map(static fn (Token $token): string => $token->text, $tokens),
            static fn (string $text): bool => $text !== '',
        ));

        self::assertSame(['SELECT', '1'], $texts);
        self::assertSame('', $tokens[count($tokens) - 1]->text);
    }

    /**
     * @return array<string, array{Tokenizer}>
     */
    public static function providerTokenizers(): array
    {
        return [
            'MySQL' => [new MySqlParser()],
            'PostgreSQL' => [new PostgreSqlParser()],
            'SQLite' => [new SqliteParser()],
        ];
    }
}
