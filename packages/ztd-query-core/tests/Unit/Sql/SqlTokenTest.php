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
    public function testEndOffsetExposesSpanNestingAndKeywordComparison(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'select', 3, 0, 0);

        self::assertSame(9, $token->endOffset());
        self::assertTrue($token->isTopLevel());
        self::assertTrue($token->isKeyword('SELECT'));
    }

    public function testNestedTokenIsNotTopLevel(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'WHERE', 0, 1, 0);

        self::assertFalse($token->isTopLevel());
    }

    public function testBracketNestedTokenIsNotTopLevel(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'WHERE', 0, 0, 1);

        self::assertFalse($token->isTopLevel());
    }

    public function testNonWordTokenIsNotAKeyword(): void
    {
        $token = new SqlToken(SqlTokenKind::String, 'SELECT', 0, 0, 0);

        self::assertFalse($token->isKeyword('SELECT'));
    }

    public function testDifferentWordIsNotAKeyword(): void
    {
        $token = new SqlToken(SqlTokenKind::Word, 'SELECTED', 0, 0, 0);

        self::assertFalse($token->isKeyword('SELECT'));
    }
}
