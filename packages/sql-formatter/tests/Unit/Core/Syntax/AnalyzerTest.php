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
final class AnalyzerTest extends TestCase
{
    public function testVisitKeepsTokensInSourceOrder(): void
    {
        $document = Document::from((new SqliteParser())->parse('SELECT id, name FROM users'), (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        self::assertSame(['SELECT', 'id', ',', 'name', 'FROM', 'users'], array_column($document->tokens, 'text'));
        self::assertSame(0, $document->clauses[0]);
        self::assertSame(4, $document->clauses[4]);
    }
}
