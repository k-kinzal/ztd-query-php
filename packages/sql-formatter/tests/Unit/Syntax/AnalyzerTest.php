<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Syntax\Document;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Document::class)]
#[CoversClass(\SqlFormatter\Syntax\Analyzer::class)]
#[CoversClass(\SqlFormatter\Syntax\Brackets::class)]
#[CoversClass(\SqlFormatter\Syntax\Expressions::class)]
#[CoversClass(\SqlFormatter\Syntax\Headers::class)]
#[CoversClass(\SqlFormatter\Syntax\Markers::class)]
#[CoversClass(\SqlFormatter\Syntax\Rules::class)]
#[CoversClass(\SqlFormatter\Syntax\Lists::class)]
final class AnalyzerTest extends TestCase
{
    public function testVisitKeepsTokensInSourceOrder(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT id, name FROM users'));
        self::assertSame(['SELECT', 'id', ',', 'name', 'FROM', 'users'], array_column($document->tokens, 'text'));
        self::assertSame(0, $document->clauses[0]);
        self::assertSame(4, $document->clauses[4]);
    }
}
