<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\DiagnosticKeywords;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(DiagnosticKeywords::class)]
final class DiagnosticKeywordsTest extends TestCase
{
    public function testContainsKeywordIgnoresQuotedText(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize("SELECT 'INTO', `UPDATE`", \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertTrue(DiagnosticKeywords::containsKeyword($tokens, ['UPDATE', 'SELECT']));
        self::assertFalse(DiagnosticKeywords::containsKeyword($tokens, ['INTO', 'UPDATE']));
        self::assertFalse(DiagnosticKeywords::containsKeyword([], ['SELECT']));
    }

}
