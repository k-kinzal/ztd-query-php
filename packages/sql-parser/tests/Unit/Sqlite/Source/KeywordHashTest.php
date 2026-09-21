<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Sqlite\Source\KeywordHash;

#[CoversClass(KeywordHash::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class KeywordHashTest extends TestCase
{
    public function testParse(): void
    {
        $source = "  { \"ABORT\",            \"TK_ABORT\",        CONFLICT|TRIGGER, 0      },\n  { \"LEFT\",             \"TK_JOIN_KW\",      ALWAYS,           0      },\n  { \"WITHIN\",           \"TK_WITHIN\",       ORDERSET,         0      },\n  { \"WINDOW\",           \"TK_WINDOW\",       WINDOWFUNC,       0      },\n";

        self::assertSame(['keywords' => ['ABORT' => 'ABORT', 'LEFT' => 'JOIN_KW', 'WINDOW' => 'WINDOW']], (new KeywordHash())->parse($source));
        self::assertSame(['keywords' => ['ABORT' => 'ABORT', 'LEFT' => 'JOIN_KW', 'WINDOW' => 'WINDOW', 'WITHIN' => 'WITHIN']], (new KeywordHash(['SQLITE_ENABLE_ORDERED_SET_AGGREGATES']))->parse($source));
        self::assertSame(['keywords' => ['ABORT' => 'ABORT', 'LEFT' => 'JOIN_KW']], (new KeywordHash(['SQLITE_OMIT_WINDOWFUNC']))->parse($source));
    }

    public function testParseRejectsAFileWithoutKeywords(): void
    {
        $this->expectException(RuntimeException::class);

        (new KeywordHash())->parse('int x;');
    }

    public function testCompiledIn(): void
    {
        $default = new KeywordHash();
        $trimmed = new KeywordHash(['SQLITE_OMIT_ALTERTABLE', 'SQLITE_OMIT_TRIGGER']);

        self::assertTrue($default->compiledIn(['ALWAYS']));
        self::assertTrue($default->compiledIn(['CONFLICT', 'TRIGGER']));
        self::assertFalse($default->compiledIn(['ORDERSET']));
        self::assertFalse($trimmed->compiledIn(['ALTER']));
        self::assertTrue($trimmed->compiledIn(['CONFLICT', 'TRIGGER']));
        self::assertFalse($trimmed->compiledIn(['TRIGGER']));
        self::assertFalse($default->compiledIn([]));
    }
}
