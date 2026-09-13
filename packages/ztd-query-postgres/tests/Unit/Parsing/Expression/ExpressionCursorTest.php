<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\ExpressionCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class ExpressionCursorTest extends TestCase
{
    public function testRetainsTheSourceAndStartsAtTheFirstToken(): void
    {
        $sql = 'id + 1';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $cursor = new \ZtdQuery\Platform\Postgres\Parsing\Expression\ExpressionCursor($sql, 'users', $tokens);
        self::assertSame('id + 1', $cursor->sql);
        self::assertSame('users', $cursor->tableName);
        self::assertSame($tokens, $cursor->tokens);
        self::assertSame(0, $cursor->index);
    }
}
