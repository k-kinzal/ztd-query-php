<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Syntax\Brackets;
use SqlFormatter\Syntax\Document;
use SqlParser\Lexer\Token;

#[CoversClass(Brackets::class)]
#[CoversClass(Document::class)]
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
