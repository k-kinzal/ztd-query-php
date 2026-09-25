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
final class ListsTest extends TestCase
{
    public function testMarkKeepsFunctionArgumentCommasInline(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT a FROM t GROUP BY coalesce(a,b), c'));
        self::assertCount(1, $document->commas);
        self::assertCount(3, $document->clauses);
    }
}
