<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\PostgreSql\Lexer\KeywordTable;
use SqlParser\PostgreSql\PostgreSqlVersion;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;

#[CoversClass(KeywordTable::class)]
#[UsesClass(PostgreSqlVersion::class)]
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
        $table = KeywordTable::load(PostgreSqlVersion::resolve()->release->keywordPath);

        self::assertSame('SELECT', $table->lookup('select'));
        self::assertSame('ABORT_P', $table->lookup('Abort'));
        self::assertNull($table->lookup('users'));
    }

    public function testLoadRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        KeywordTable::load('/nowhere/keywords.php');
    }

    public function testLookup(): void
    {
        $table = new KeywordTable(['NCHAR' => 'NCHAR']);

        self::assertSame('NCHAR', $table->lookup('nchar'));
        self::assertNull($table->lookup('n'));
    }
}
