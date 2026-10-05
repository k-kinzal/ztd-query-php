<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Platform\Sqlite\Rules\LeafKeys;

#[CoversClass(LeafKeys::class)]
#[Small]
final class LeafKeysTest extends TestCase
{
    public function testKeyAnswersNullForAnEmptyTokenAndForADeclaredNoisePosition(): void
    {
        $keys = new LeafKeys();

        self::assertNull($keys->key(new Token(1, 'SEMI', '', 8), 'ecmd: cmdx SEMI', 1));
        self::assertNull($keys->key(new Token(1, 'AS', 'as', 8), 'as: AS nm', 0));
        self::assertNull($keys->key(new Token(1, 'EQ', '==', 8), 'setlist: nm EQ expr', 1));
    }

    public function testKeyKeysAnIdentifierPositionByItsDecodedName(): void
    {
        $keys = new LeafKeys();

        self::assertSame('name:My Table', $keys->key(new Token(1, 'ID', '"My Table"', 0), 'nm: idj', 0));
        self::assertSame('name:My Table', $keys->key(new Token(1, 'ID', '[My Table]', 0), 'nm: idj', 0));
        self::assertSame('name:t', $keys->key(new Token(1, 'STRING', "'t'", 0), 'nm: STRING', 0));
        self::assertSame('name:nocase', $keys->key(new Token(1, 'ID', 'nocase', 0), 'collate: COLLATE ids', 1));
        self::assertSame('name:b', $keys->key(new Token(1, 'ID', '`b`', 0), 'as: ids', 0));
    }

    public function testKeyKeysAnUnqualifiedWordUsedAsAValueByTheRequestItIs(): void
    {
        $keys = new LeafKeys();

        self::assertSame('name:a', $keys->key(new Token(1, 'ID', 'a', 0), 'expr: idj', 0));
        self::assertSame('quoted:a', $keys->key(new Token(1, 'ID', '"a"', 0), 'expr: idj', 0));
        self::assertSame('truth:true', $keys->key(new Token(1, 'ID', 'TRUE', 0), 'expr: idj', 0));
    }

    public function testKeyKeysAKeywordByItsTerminalAndUpperCasedText(): void
    {
        $keys = new LeafKeys();

        self::assertSame('SELECT:SELECT', $keys->key(new Token(1, 'SELECT', 'select', 0), 'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt orderby_opt limit_opt', 0));
        self::assertSame('EQ:=', $keys->key(new Token(1, 'EQ', '=', 0), 'expr: expr EQ|NE expr', 1));
        self::assertSame('EQ:==', $keys->key(new Token(1, 'EQ', '==', 0), 'expr: expr EQ|NE expr', 1));
        self::assertSame('TEMP:TEMP', $keys->key(new Token(1, 'TEMP', 'temporary', 0), 'temp: TEMP', 0));
    }

    public function testKeyKeysAWordBeforeJoinAsTheJoinKeywordItIsWrittenAs(): void
    {
        $keys = new LeafKeys();

        self::assertSame('JOIN_KW:LEFT', $keys->key(new Token(1, 'JOIN_KW', 'left', 0), 'joinop: JOIN_KW nm JOIN', 0));
        self::assertSame('JOIN_KW:OUTER', $keys->key(new Token(1, 'JOIN_KW', 'outer', 5), 'nm: idj', 0));
        self::assertSame('name:outer', $keys->key(new Token(1, 'JOIN_KW', 'outer', 5), 'nm: idj', 0));
    }

    public function testKeyKeysANameBeforeJoinByItsNameWhenItIsNoJoinKeyword(): void
    {
        $keys = new LeafKeys();

        self::assertSame('JOIN_KW:LEFT', $keys->key(new Token(1, 'JOIN_KW', 'LEFT', 0), 'joinop: JOIN_KW nm nm JOIN', 0));
        self::assertSame('name:bogus', $keys->key(new Token(1, 'ID', 'bogus', 5), 'nm: idj', 0));
        self::assertSame('JOIN_KW:INNER', $keys->key(new Token(1, 'JOIN_KW', 'inner', 11), 'nm: idj', 0));
        self::assertSame('name:inner', $keys->key(new Token(1, 'JOIN_KW', 'inner', 17), 'nm: idj', 0));
    }

    public function testKeyCountsTheJoinWordsOfTheLatestOperatorOnly(): void
    {
        $keys = new LeafKeys();

        self::assertSame('JOIN_KW:LEFT', $keys->key(new Token(1, 'JOIN_KW', 'LEFT', 0), 'joinop: JOIN_KW nm JOIN', 0));
        self::assertSame('JOIN_KW:CROSS', $keys->key(new Token(1, 'JOIN_KW', 'CROSS', 0), 'joinop: JOIN_KW JOIN', 0));
        self::assertSame('name:outer', $keys->key(new Token(1, 'JOIN_KW', 'outer', 5), 'nm: idj', 0));
    }

    public function testWordKeysADoubleQuotedWordApartFromABareName(): void
    {
        $keys = new LeafKeys();

        self::assertSame('quoted:x', $keys->word(new Token(1, 'ID', '"x"', 0)));
        self::assertSame('name:x', $keys->word(new Token(1, 'ID', 'x', 0)));
        self::assertSame('name:x', $keys->word(new Token(1, 'ID', '`x`', 0)));
        self::assertSame('name:x', $keys->word(new Token(1, 'ID', '[x]', 0)));
    }

    public function testWordKeysTheBareWordsTrueAndFalseAsTruthValues(): void
    {
        $keys = new LeafKeys();

        self::assertSame('truth:true', $keys->word(new Token(1, 'ID', 'true', 0)));
        self::assertSame('truth:false', $keys->word(new Token(1, 'ID', 'False', 0)));
        self::assertSame('name:true', $keys->word(new Token(1, 'ID', '`true`', 0)));
        self::assertSame('quoted:true', $keys->word(new Token(1, 'ID', '"true"', 0)));
    }

    public function testLiteralKeysAStringByItsDecodedText(): void
    {
        self::assertSame("string:it's", (new LeafKeys())->literal(new Token(1, 'STRING', "'it''s'", 0)));
    }

    public function testLiteralKeysABlobByItsUpperCasedHexadecimalDigits(): void
    {
        self::assertSame('blob:0AFF', (new LeafKeys())->literal(new Token(1, 'BLOB', "x'0aFf'", 0)));
        self::assertSame('blob:0AFF', (new LeafKeys())->literal(new Token(1, 'BLOB', "X'0AFF'", 0)));
    }

    public function testLiteralKeysANumberWithoutDigitSeparatorsAndWithoutRegardToCase(): void
    {
        $keys = new LeafKeys();

        self::assertSame('number:1000', $keys->literal(new Token(1, 'QNUMBER', '1_000', 0)));
        self::assertSame('number:1000', $keys->literal(new Token(1, 'INTEGER', '1000', 0)));
        self::assertSame('number:0X1F', $keys->literal(new Token(1, 'INTEGER', '0x1f', 0)));
        self::assertSame('number:1.5E-3', $keys->literal(new Token(1, 'FLOAT', '1.5e-3', 0)));
    }

    public function testLiteralKeysABindParameterByItsExactText(): void
    {
        self::assertSame('parameter::Name', (new LeafKeys())->literal(new Token(1, 'VARIABLE', ':Name', 0)));
        self::assertSame('parameter:?1', (new LeafKeys())->literal(new Token(1, 'VARIABLE', '?1', 0)));
    }

    public function testLiteralKeysAnyOtherTokenByItsTerminalAndUpperCasedText(): void
    {
        $keys = new LeafKeys();

        self::assertSame('LP:(', $keys->literal(new Token(1, 'LP', '(', 0)));
        self::assertSame('NE:!=', $keys->literal(new Token(1, 'NE', '!=', 0)));
        self::assertSame('NE:<>', $keys->literal(new Token(1, 'NE', '<>', 0)));
        self::assertSame('ORDER:ORDER', $keys->literal(new Token(1, 'ORDER', 'Order', 0)));
        self::assertSame('TEMP:TEMP', $keys->literal(new Token(1, 'TEMP', 'TEMPORARY', 0)));
    }

    public function testSynonymousAcceptsOnlyTheSameTerminal(): void
    {
        $keys = new LeafKeys();

        self::assertTrue($keys->synonymous(new Token(1, 'AND', 'AND', 0), new Token(1, 'AND', 'and', 0)));
        self::assertFalse($keys->synonymous(new Token(1, 'AND', 'AND', 0), new Token(1, 'OR', 'OR', 0)));
    }
}
