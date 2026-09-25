<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Syntax\Document;
use SqlFormatter\Syntax\Headers;
use SqlParser\Lexer\Token;

#[CoversClass(Headers::class)]
#[CoversClass(Document::class)]
final class HeadersTest extends TestCase
{
    public function testMarkRecognizesLongestHeader(): void
    {
        $document = new Document('');
        $document->tokens = [new Token(1, 'LEFT', 'LEFT', 0), new Token(2, 'OUTER', 'OUTER', 5), new Token(3, 'JOIN', 'JOIN', 11)];
        (new Headers($document))->mark(0);
        self::assertSame([0 => 2], $document->clauses);
    }

    public function testMarkDoesNotTreatIdentifierAsClause(): void
    {
        $document = new Document('');
        $document->tokens = [new Token(1, 'IDENT', 'users', 0)];
        (new Headers($document))->mark(0);
        self::assertSame([], $document->clauses);
    }
}
