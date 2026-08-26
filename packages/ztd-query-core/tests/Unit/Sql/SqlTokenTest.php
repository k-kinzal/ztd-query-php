<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(SqlToken::class)]
final class SqlTokenTest extends TestCase
{
    public function testEndOffsetIsJustPastTheLastByteOfTheLexeme(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'select', 3, 0, 0);

        self::assertSame(9, $token->endOffset());
    }

    public function testIsTopLevelWhenTheTokenIsInsideNoParenthesisAndNoBracket(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'select', 3, 0, 0);

        self::assertTrue($token->isTopLevel());
    }

    public function testIsKeywordDoesNotMindHowTheWordWasCased(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'select', 3, 0, 0);

        self::assertTrue($token->isKeyword('SELECT'));
    }

    public function testIsTopLevelIsFalseForATokenInsideAParenthesis(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'WHERE', 0, 1, 0);

        self::assertFalse($token->isTopLevel());
    }

    public function testIsTopLevelIsFalseForATokenInsideABracket(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'WHERE', 0, 0, 1);

        self::assertFalse($token->isTopLevel());
    }

    public function testIsKeywordIsFalseForAKeywordSpelledInsideAString(): void
    {
        $token = new SqlToken(SqlTokenKind::String, 'SELECT', 0, 0, 0);

        self::assertFalse($token->isKeyword('SELECT'));
    }

    public function testIsKeywordIsFalseForAWordThatMerelyStartsWithIt(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'SELECTED', 0, 0, 0);

        self::assertFalse($token->isKeyword('SELECT'));
    }
}
