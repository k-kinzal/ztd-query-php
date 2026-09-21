<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\PostgreSql\Source\KeywordList;

#[CoversClass(KeywordList::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class KeywordListTest extends TestCase
{
    public function testParse(): void
    {
        $source = "PG_KEYWORD(\"select\", SELECT, RESERVED_KEYWORD, BARE_LABEL)\nPG_KEYWORD(\"abort\", ABORT_P, UNRESERVED_KEYWORD, BARE_LABEL)\n";

        self::assertSame(['keywords' => ['ABORT' => 'ABORT_P', 'SELECT' => 'SELECT']], (new KeywordList())->parse($source));
    }

    public function testParseRejectsAFileWithoutKeywords(): void
    {
        $this->expectException(RuntimeException::class);

        (new KeywordList())->parse('int x;');
    }
}
