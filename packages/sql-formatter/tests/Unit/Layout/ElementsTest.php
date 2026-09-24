<?php

declare(strict_types=1);

namespace Tests\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\FormatOptions;
use SqlFormatter\Layout\Renderer;
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
#[CoversClass(Renderer::class)]
#[CoversClass(\SqlFormatter\Layout\Elements::class)]
#[CoversClass(\SqlFormatter\Layout\Headers::class)]
#[CoversClass(\SqlFormatter\Layout\Policy::class)]
#[CoversClass(\SqlFormatter\Layout\Spacing::class)]
#[CoversClass(\SqlFormatter\Layout\Writer::class)]
#[CoversClass(FormatOptions::class)]
#[CoversClass(\SqlFormatter\Layout\Block::class)]
#[CoversClass(\SqlFormatter\Syntax\Lists::class)]
final class ElementsTest extends TestCase
{
    public function testWriteFormatsCaseBranches(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT CASE WHEN a=1 THEN 2 ELSE 3 END FROM t'));
        self::assertSame("SELECT\n    CASE\n        WHEN a = 1 THEN 2\n        ELSE 3\n    END\nFROM\n    t", (new Renderer($document, new FormatOptions(Style::Expanded)))->render());
    }
}
