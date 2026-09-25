<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\Syntax\Brackets;
use SqlFormatter\Core\Syntax\Document;
use SqlParser\Lexer\Token;

#[CoversClass(Brackets::class)]
#[CoversClass(Document::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class BracketsTest extends TestCase
{
    public function testMarkPairsNestedParentheses(): void
    {
        $document = new Document('');
        $document->tokens = [new Token(1, 'LP', '(', 0), new Token(2, 'SELECT', 'SELECT', 1), new Token(1, 'LP', '(', 7), new Token(3, 'NUM', '1', 8), new Token(4, 'RP', ')', 9), new Token(4, 'RP', ')', 10)];
        $document->clauses = [1 => 1];
        Brackets::mark($document);
        self::assertSame([2 => 4, 0 => 5], $document->pairs);
        self::assertSame([0 => true], $document->blocks);
    }
}
