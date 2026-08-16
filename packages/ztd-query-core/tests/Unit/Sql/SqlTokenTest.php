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
    public function testExposesSpanNestingAndKeywordComparison(): void
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
}
