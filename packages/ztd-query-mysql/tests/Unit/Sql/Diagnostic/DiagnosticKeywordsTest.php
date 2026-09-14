<?php

declare(strict_types=1);

namespace Tests\Unit\Sql\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Sql\Diagnostic\DiagnosticKeywords;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[CoversClass(DiagnosticKeywords::class)]
final class DiagnosticKeywordsTest extends TestCase
{
    public function testContainsKeywordIgnoresQuotedText(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize("SELECT 'INTO', `UPDATE`", \ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::create())->significantTokens();
        self::assertTrue(DiagnosticKeywords::containsKeyword($tokens, ['UPDATE', 'SELECT']));
        self::assertFalse(DiagnosticKeywords::containsKeyword($tokens, ['INTO', 'UPDATE']));
        self::assertFalse(DiagnosticKeywords::containsKeyword([], ['SELECT']));
    }

}
