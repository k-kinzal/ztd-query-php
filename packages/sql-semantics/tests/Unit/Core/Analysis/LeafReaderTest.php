<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\LeafReader;
use SqlSemantics\Core\Language;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Writer;

#[CoversClass(LeafReader::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class LeafReaderTest extends TestCase
{
    public function testTerminalNamesTheOneTokenOfASpelling(): void
    {
        $reader = new LeafReader(new Language(MySqlDialect::MySql));
        self::assertSame('IDENT', $reader->terminal('users'));
        self::assertSame('IDENT_QUOTED', $reader->terminal('`select`'));
        self::assertSame('SELECT_SYM', $reader->terminal('select'));
        self::assertSame('LONG_NUM', $reader->terminal('9223372036854775807'));
        self::assertNull($reader->terminal('a b'));
        self::assertNull($reader->terminal(' a'));
        self::assertNull($reader->terminal("'unterminated"));
        self::assertNull($reader->terminal(''));
    }

    public function testReadAnswersTheLeafARoleAdmitsOrNothing(): void
    {
        $reader = new LeafReader(new Language(MySqlDialect::MySql));
        self::assertSame('`select`', Writer::render($reader->read('ident', '`select`') ?? self::fail('quoted')));
        self::assertSame('action', Writer::render($reader->read('ident', 'action') ?? self::fail('keyword')));
        self::assertNull($reader->read('ident', 'select'));
        self::assertNull($reader->read('ident', '1e3'));
        self::assertSame('NULL', Writer::render($reader->read('simple_expr', 'NULL') ?? self::fail('null')));
        self::assertSame('TRUE', Writer::render($reader->read('simple_expr', 'TRUE') ?? self::fail('true')));
    }

    public function testTokensNameTheTerminalsOfAText(): void
    {
        $reader = new LeafReader(new Language(MySqlDialect::MySql));
        self::assertSame(['SELECT_SYM', 'NUM'], $reader->tokens('SELECT 1'));
        self::assertSame([], $reader->tokens(''));
    }

    public function testTerminalReadsAfterAPrefixAsTheLexerWould(): void
    {
        $reader = new LeafReader(new Language(MySqlDialect::MySql));
        self::assertSame('IDENT', $reader->terminal('select', '.'));
        self::assertSame('SELECT_SYM', $reader->terminal('select'));
        self::assertSame('select', Writer::render($reader->read('ident', 'select', '.') ?? self::fail('after a dot')));
    }

    public function testReadFollowsFallbacksOfTheLanguage(): void
    {
        $reader = new LeafReader(new Language(SqliteDialect::Sqlite));
        self::assertSame('key', Writer::render($reader->read('nm', 'key') ?? self::fail('fallback')));
        self::assertNull($reader->read('nm', 'select'));
        self::assertSame(['SELECT', 'INTEGER'], $reader->tokens('SELECT 1'));
    }
}
