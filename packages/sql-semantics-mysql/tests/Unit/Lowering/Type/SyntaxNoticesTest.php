<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Lowering\Type\SyntaxNotices;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(SyntaxNotices::class)]
#[Medium]
final class SyntaxNoticesTest extends TestCase
{
    public function testCollationCompletesBeforeADeclarationMismatch(): void
    {
        $sql = 'CREATE PROCEDURE p(x INT COLLATE ucs2_bin) BEGIN END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $notices[0][1]);
        self::assertSame(4079, $notices[0][1]->code());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[1][1]);
    }

    /**
     * @return iterable<string, array{string, list<Deprecated>}>
     */
    public static function providerAttributes(): iterable
    {
        yield 'numeric digits' => ['FLOAT(3,1)', [Deprecated::FloatingDigits]];
        yield 'integer display width' => ['INT(3)', [Deprecated::DisplayWidth]];
        yield 'numeric attributes' => ['DOUBLE(3,1) UNSIGNED ZEROFILL', [Deprecated::Zerofill, Deprecated::FloatingDigits, Deprecated::UnsignedFraction]];
        yield 'binary attribute' => ['TEXT BINARY', [Deprecated::BinaryAttribute]];
        yield 'leading binary' => ['TEXT BINARY ASCII', [Deprecated::BinaryAttribute, Deprecated::AsciiCharset]];
        yield 'trailing binary' => ['TEXT UNICODE BINARY', [Deprecated::UnicodeCharset, Deprecated::BinaryAttribute]];
        yield 'named charset' => ['TEXT CHARACTER SET utf8mb4 BINARY', [Deprecated::BinaryAttribute]];
        yield 'deprecated charset' => ['TEXT CHARACTER SET utf8mb3 BINARY', [Deprecated::Utf8mb3, Deprecated::BinaryAttribute]];
        yield 'byte' => ['TEXT BYTE', []];
        yield 'binary type' => ['BINARY(2)', []];
        yield 'invalid attribute' => ['INT BINARY', []];
    }

    /**
     * @param list<Deprecated> $expected The completed attribute warnings
     */
    #[DataProvider('providerAttributes')]
    public function testCompletedKeepsAttributeOrderBeforeARefusedMarker(string $type, array $expected): void
    {
        $sql = 'CREATE TABLE t(a ' . $type . ', b INT DEFAULT ?)';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '?'), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame($expected, array_column($notices, 1));
    }

    public function testCompletedKeepsDatabaseCharsetWarningsBeforeALaterOption(): void
    {
        $sql = 'ALTER DATABASE d CHARACTER SET utf8 READ ONLY 2';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '2'), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([Deprecated::Utf8Alias], array_column($notices, 1));
    }
}
