<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Core\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Visitor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class DocumentTest extends TestCase
{
    public function testSignatureIgnoresOptionalSyntaxButPreservesOperatorAssociation(): void
    {
        $parser = new \SqlParser\Sqlite\SqliteParser();
        $left = new \SqlFormatter\Core\Compact\Document($parser->parse('select all (a+b)+c as x from t order by a asc;'), (new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules());
        $same = new \SqlFormatter\Core\Compact\Document($parser->parse('SELECT a+b+c x FROM t ORDER BY a'), (new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules());
        $different = new \SqlFormatter\Core\Compact\Document($parser->parse('SELECT a+(b+c) x FROM t ORDER BY a'), (new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules());
        self::assertSame($left->signature(), $same->signature());
        self::assertNotSame($left->signature(), $different->signature());
    }

    public function testSignatureDistinguishesHintsAndExecutableComments(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser();
        $plain = new \SqlFormatter\Core\Compact\Document($parser->parse('SELECT 1'), (new \SqlFormatter\Platform\MySql\Dialect())->compactRules());
        $hinted = new \SqlFormatter\Core\Compact\Document($parser->parse('SELECT /*+ MAX_EXECUTION_TIME(1) */ 1'), (new \SqlFormatter\Platform\MySql\Dialect())->compactRules());
        $versioned = new \SqlFormatter\Core\Compact\Document($parser->parse('SELECT /*!99999 DISTINCT */ 1'), (new \SqlFormatter\Platform\MySql\Dialect())->compactRules());
        self::assertNotSame($plain->signature(), $hinted->signature());
        self::assertNotSame($plain->signature(), $versioned->signature());
    }
}
