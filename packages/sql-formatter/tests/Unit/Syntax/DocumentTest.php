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
final class DocumentTest extends TestCase
{
    public function testFromDistinguishesFunctionAndProjectionCommas(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT coalesce(a,b), c FROM t'));
        self::assertCount(1, $document->commas);
        self::assertCount(1, $document->pairs);
        self::assertSame([], $document->blocks);
    }
}
