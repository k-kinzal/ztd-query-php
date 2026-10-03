<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\Identifier as Subject;
use SqlParser\Lexer\Token;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\CodePoint::class)]
final class IdentifierTest extends TestCase
{
    public function testDecodeKeepsPlainNamesAndUnfoldsQuotedOnes(): void
    {
        $tree = (new PostgreSqlParser())->parse('CREATE TABLE t (Plain INT, "Quo""ted" INT, name INT)');
        $names = array_map(static fn ($node): string => (new Subject())->decode($node->tokens()[0]), $tree->find('columnDef'));

        self::assertSame(['Plain', 'Quo"ted', 'name'], $names);
        self::assertSame('x', (new Subject())->decode(new Token(1, 'IDENT', 'x', 0)));
    }

    public function testFoldLowercasesOnlyTheNamesWrittenWithoutQuotes(): void
    {
        self::assertSame('users', (new Subject())->fold(new Token(1, 'IDENT', 'Users', 0)));
        self::assertSame('Users', (new Subject())->fold(new Token(1, 'IDENT', '"Users"', 0)));
        self::assertSame('a"b', (new Subject())->fold(new Token(1, 'IDENT', '"a""b"', 0)));
    }

    public function testQuoteWritesOneIdentifierWithItsQuoteDoubled(): void
    {
        self::assertSame('"users"', (new Subject())->quote('users'));
        self::assertSame('"a""b"', (new Subject())->quote('a"b'));
        self::assertSame('"My.Table"', (new Subject())->quote('My.Table'));
    }

    public function testIsQuotedTellsTheWritingsApart(): void
    {
        self::assertTrue((new Subject())->isQuoted('"users"'));
        self::assertTrue((new Subject())->isQuoted('U&"users"'));
        self::assertTrue((new Subject())->isQuoted('u&"users"'));
        self::assertFalse((new Subject())->isQuoted('users'));
    }

    public function testIsUnicodeQuotedAcceptsOnlyTheEscapedWriting(): void
    {
        self::assertTrue((new Subject())->isUnicodeQuoted('U&"users"'));
        self::assertTrue((new Subject())->isUnicodeQuoted('u&"users"'));
        self::assertFalse((new Subject())->isUnicodeQuoted('"users"'));
        self::assertFalse((new Subject())->isUnicodeQuoted('U&'));
    }

    public function testDecodeReadsTheUnicodeEscapesOfAnIdentifier(): void
    {
        self::assertSame('data', (new Subject())->decode(new Token(1, 'IDENT', 'U&"d\\0061ta"', 0)));
        self::assertSame('😀', (new Subject())->decode(new Token(1, 'IDENT', 'U&"\\+01F600"', 0)));
        self::assertSame('users', (new Subject())->fold(new Token(1, 'IDENT', 'U&"users"', 0)));
    }

    public function testUnescapeKeepsWhatIsNotAnEscape(): void
    {
        self::assertSame('\\', (new Subject())->unescape('\\\\'));
        self::assertSame('\\zz', (new Subject())->unescape('\\zz'));
        self::assertSame('plain', (new Subject())->unescape('plain'));
        self::assertSame('\\00', (new Subject())->unescape('\\00'));
    }
}
