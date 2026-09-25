<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\Syntax\Document;
use SqlFormatter\Core\Syntax\Headers;
use SqlParser\Lexer\Token;

#[CoversClass(Headers::class)]
#[CoversClass(Document::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class HeadersTest extends TestCase
{
    public function testMarkRecognizesLongestHeader(): void
    {
        $document = new Document('');
        $document->tokens = [new Token(1, 'LEFT', 'LEFT', 0), new Token(2, 'OUTER', 'OUTER', 5), new Token(3, 'JOIN', 'JOIN', 11)];
        (new Headers($document, (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules()))->mark(0);
        self::assertSame([0 => 2], $document->clauses);
    }

    public function testMarkDoesNotTreatIdentifierAsClause(): void
    {
        $document = new Document('');
        $document->tokens = [new Token(1, 'IDENT', 'users', 0)];
        (new Headers($document, (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules()))->mark(0);
        self::assertSame([], $document->clauses);
    }
}
