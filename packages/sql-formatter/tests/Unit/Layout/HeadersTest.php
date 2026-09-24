<?php

declare(strict_types=1);

namespace Tests\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\FormatOptions;
use SqlFormatter\Layout\Headers;
use SqlFormatter\Layout\Policy;
use SqlFormatter\Layout\Writer;
use SqlFormatter\Style;
use SqlFormatter\Syntax\Document;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Document::class)]
#[CoversClass(\SqlFormatter\Syntax\Analyzer::class)]
#[CoversClass(\SqlFormatter\Syntax\Brackets::class)]
#[CoversClass(\SqlFormatter\Syntax\Expressions::class)]
#[CoversClass(\SqlFormatter\Syntax\Headers::class)]
#[CoversClass(\SqlFormatter\Syntax\Markers::class)]
#[CoversClass(\SqlFormatter\Syntax\Rules::class)]
#[CoversClass(Headers::class)]
#[CoversClass(Writer::class)]
#[CoversClass(Policy::class)]
#[CoversClass(FormatOptions::class)]
#[CoversClass(\SqlFormatter\Syntax\Lists::class)]
final class HeadersTest extends TestCase
{
    public function testWidthExcludesNestedQueryHeaders(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT (SELECT a FROM t ORDER BY a) FROM u'));
        $headers = new Headers($document);
        self::assertSame(6, $headers->width(0, count($document->tokens) - 1));
        self::assertSame(8, $headers->width(2, 9));
    }
    public function testLengthIncludesInterwordSpaces(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT a FROM t ORDER BY a'));
        self::assertSame(8, (new Headers($document))->length(4, 5));
    }

    public function testWriteAlignsRiverHeader(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT a FROM t'));
        $writer = new Writer();
        (new Headers($document))->write($writer, 0, 0, new Policy(new FormatOptions(Style::River), 0, 8));
        self::assertSame('  SELECT', $writer->finish(''));
    }
}
