<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Sqlite\Lexer\KeywordTable;
use SqlParser\Sqlite\SqliteVersion;

#[CoversClass(KeywordTable::class)]
#[UsesClass(SqliteVersion::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(VersionRegistry::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class KeywordTableTest extends TestCase
{
    public function testLoad(): void
    {
        $table = KeywordTable::load(SqliteVersion::resolve()->release->keywordPath);

        self::assertSame('JOIN_KW', $table->lookup('left'));
        self::assertSame('LIKE_KW', $table->lookup('GLOB'));
        self::assertNull($table->lookup('within'));
    }

    public function testLoadRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        KeywordTable::load('/nowhere/keywords.php');
    }

    public function testLookup(): void
    {
        $table = new KeywordTable(['SELECT' => 'SELECT']);

        self::assertSame('SELECT', $table->lookup('select'));
        self::assertNull($table->lookup('users'));
    }
}
