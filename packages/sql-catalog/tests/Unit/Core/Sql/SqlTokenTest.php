<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Sql\SqlToken;
use SqlCatalog\Core\Sql\SqlTokenKind;

#[CoversClass(SqlToken::class)]
final class SqlTokenTest extends TestCase
{
    public function testKeywordUpperCasesTheToken(): void
    {
        self::assertSame('SELECT', (new SqlToken(SqlTokenKind::Word, 'select', 0, 0))->keyword());
    }

    public function testIsTopLevelKeywordNeedsTheRightWordKindAndDepth(): void
    {
        self::assertTrue((new SqlToken(SqlTokenKind::Word, 'select', 0, 0))->isTopLevelKeyword('SELECT'));
        self::assertFalse((new SqlToken(SqlTokenKind::Word, 'select', 0, 1))->isTopLevelKeyword('SELECT'));
        self::assertFalse((new SqlToken(SqlTokenKind::Text, 'select', 0, 0))->isTopLevelKeyword('SELECT'));
        self::assertFalse((new SqlToken(SqlTokenKind::Word, 'from', 0, 0))->isTopLevelKeyword('SELECT'));
    }
}
