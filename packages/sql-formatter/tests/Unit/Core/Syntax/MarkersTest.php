<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\Syntax\Document;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Document::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Analyzer::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Brackets::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Expressions::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Headers::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Markers::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Rules::class)]
#[CoversClass(\SqlFormatter\Core\Syntax\Lists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class MarkersTest extends TestCase
{
    public function testApplyPreservesBetweenConjunction(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT a FROM t WHERE a BETWEEN 1 AND 3 AND b=1'), (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        self::assertCount(1, $document->logical);
        self::assertCount(3, $document->clauses);
    }
}
