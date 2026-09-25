<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Layout\Headers;
use SqlFormatter\Core\Layout\Policy;
use SqlFormatter\Core\Layout\Writer;
use SqlFormatter\Core\Style;
use SqlFormatter\Core\Syntax\Document;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Document::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Analyzer::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Brackets::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Expressions::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Headers::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Markers::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Rules::class)]
#[CoversClass(Headers::class)]
#[CoversClass(Writer::class)]
#[CoversClass(Policy::class)]
#[CoversClass(FormatOptions::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Lists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class HeadersTest extends TestCase
{
    public function testWidthExcludesNestedQueryHeaders(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT (SELECT a FROM t ORDER BY a) FROM u'), (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        $headers = new Headers($document);
        self::assertSame(6, $headers->width(0, count($document->tokens) - 1));
        self::assertSame(8, $headers->width(2, 9));
    }
    public function testLengthIncludesInterwordSpaces(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT a FROM t ORDER BY a'), (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        self::assertSame(8, (new Headers($document))->length(4, 5));
    }

    public function testWriteAlignsRiverHeader(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT a FROM t'), (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        $writer = new Writer();
        (new Headers($document))->write($writer, 0, 0, new Policy(new FormatOptions(Style::River), 0, 8));
        self::assertSame('  SELECT', $writer->finish(''));
    }
}
