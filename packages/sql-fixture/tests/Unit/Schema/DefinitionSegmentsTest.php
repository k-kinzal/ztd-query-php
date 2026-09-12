<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\DefinitionSegments as Subject;

#[CoversClass(Subject::class)]
final class DefinitionSegmentsTest extends TestCase
{
    public function testSplitKeepsQuotedCommasParenthesesAndDoubledQuotes(): void
    {
        self::assertSame(["label TEXT DEFAULT 'a,b(c)'", "note TEXT DEFAULT 'it''s, fine'", 'price NUMERIC(8,2)'], (new Subject())->split("label TEXT DEFAULT 'a,b(c)', note TEXT DEFAULT 'it''s, fine', price NUMERIC(8,2)"));
    }

    public function testSplitSkipsTrailingWhitespace(): void
    {
        self::assertSame(['id INT'], (new Subject())->split('id INT,  '));
        self::assertSame([], (new Subject())->split(' '));
    }
}
