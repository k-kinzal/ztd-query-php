<?php

declare(strict_types=1);

namespace Tests\Unit\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Compact\Visitor::class)]
final class DocumentTest extends TestCase
{
    public function testSignatureIgnoresOptionalSyntaxButPreservesOperatorAssociation(): void
    {
        $parser = new \SqlParser\Sqlite\SqliteParser();
        $left = new \SqlFormatter\Compact\Document($parser->parse('select all (a+b)+c as x from t order by a asc;'), false, false);
        $same = new \SqlFormatter\Compact\Document($parser->parse('SELECT a+b+c x FROM t ORDER BY a'), false, false);
        $different = new \SqlFormatter\Compact\Document($parser->parse('SELECT a+(b+c) x FROM t ORDER BY a'), false, false);
        self::assertSame($left->signature(), $same->signature());
        self::assertNotSame($left->signature(), $different->signature());
    }

    public function testSignatureDistinguishesHintsAndExecutableComments(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser();
        $plain = new \SqlFormatter\Compact\Document($parser->parse('SELECT 1'), true, false);
        $hinted = new \SqlFormatter\Compact\Document($parser->parse('SELECT /*+ MAX_EXECUTION_TIME(1) */ 1'), true, false);
        $versioned = new \SqlFormatter\Compact\Document($parser->parse('SELECT /*!99999 DISTINCT */ 1'), true, false);
        self::assertNotSame($plain->signature(), $hinted->signature());
        self::assertNotSame($plain->signature(), $versioned->signature());
    }
}
