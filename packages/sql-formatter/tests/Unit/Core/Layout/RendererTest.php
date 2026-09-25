<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Layout\Renderer;
use SqlFormatter\Core\Syntax\Document;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Document::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Analyzer::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Brackets::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Expressions::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Headers::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Markers::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Rules::class)]
#[CoversClass(Renderer::class)]
#[CoversClass(\SqlFormatter\Core\Layout\Elements::class)]
#[CoversClass(\SqlFormatter\Core\Layout\Headers::class)]
#[CoversClass(\SqlFormatter\Core\Layout\Policy::class)]
#[CoversClass(\SqlFormatter\Core\Layout\Spacing::class)]
#[CoversClass(\SqlFormatter\Core\Layout\Writer::class)]
#[CoversClass(FormatOptions::class)]
#[CoversClass(\SqlFormatter\Core\Layout\Block::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Lists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class RendererTest extends TestCase
{
    public function testRenderExpandedPreservesSpelling(): void
    {
        $document = Document::from((new SqliteParser())->parse('select a,b from t'), (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        self::assertSame("select\n    a,\n    b\nfrom\n    t", (new Renderer($document, new FormatOptions()))->render());
    }
}
