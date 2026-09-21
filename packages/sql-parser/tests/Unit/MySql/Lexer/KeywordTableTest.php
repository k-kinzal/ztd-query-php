<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\MySql\Lexer\KeywordTable;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;

#[CoversClass(KeywordTable::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(SqlMode::class)]
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
        $table = KeywordTable::load(MySqlVersion::resolve('mysql-8.4.7')->release->keywordPath);

        self::assertSame('SELECT_SYM', $table->lookup('select', false, new SqlMode()));
        self::assertSame('EQUAL_SYM', $table->lookup('<=>', false, new SqlMode()));
    }

    public function testLoadRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        KeywordTable::load('/nowhere/keywords.php');
    }

    public function testLookup(): void
    {
        $table = new KeywordTable(['SELECT' => 'SELECT_SYM', 'NOT' => 'NOT_SYM', '||' => 'OR_OR_SYM'], ['COUNT' => 'COUNT_SYM']);
        $mode = new SqlMode();

        self::assertSame('SELECT_SYM', $table->lookup('Select', false, $mode));
        self::assertNull($table->lookup('count', false, $mode));
        self::assertSame('COUNT_SYM', $table->lookup('count', true, $mode));
        self::assertNull($table->lookup('users', true, $mode));
    }

    public function testLookupFollowsTheMode(): void
    {
        $table = new KeywordTable(['NOT' => 'NOT_SYM', '||' => 'OR_OR_SYM'], []);

        self::assertSame('NOT_SYM', $table->lookup('NOT', false, new SqlMode()));
        self::assertSame('NOT2_SYM', $table->lookup('NOT', false, new SqlMode(highNotPrecedence: true)));
        self::assertSame('OR2_SYM', $table->lookup('||', false, new SqlMode()));
        self::assertSame('OR_OR_SYM', $table->lookup('||', false, new SqlMode(pipesAsConcat: true)));
    }

    public function testHas(): void
    {
        $table = new KeywordTable(['SELECT' => 'SELECT_SYM'], ['COUNT' => 'COUNT_SYM']);

        self::assertTrue($table->has('select'));
        self::assertFalse($table->has('count'));
    }
}
