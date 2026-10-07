<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Strings::class)]
#[Small]
final class StringsTest extends TestCase
{
    public function testRoutinesNamesTheStringFunctionsThatBuildStrings(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Strings())->routines());

        self::assertSame(['CONCAT', 'CONCAT_WS', 'UPPER', 'UCASE', 'LOWER', 'LCASE', 'LEFT', 'RIGHT', 'SUBSTRING', 'SUBSTR', 'MID', 'REPLACE', 'REVERSE', 'REPEAT', 'LPAD', 'RPAD', 'LTRIM', 'RTRIM', 'SPACE', 'HEX', 'UNHEX', 'SUBSTRING_INDEX', 'INSERT'], $names);
    }

    public function testLengthAnswersTheDisplayLengthOfADomain(): void
    {
        $strings = new Strings();

        self::assertSame(22, $strings->length(Domain::double()));
        self::assertSame(10, $strings->length(Domain::double(10, 2)));
        self::assertSame(0, $strings->length(Domain::null()));
        self::assertSame(12, $strings->length(Domain::string(12, Collation::known('utf8mb4_0900_ai_ci'))));
        self::assertSame(11, $strings->length(new Domain(Kind::Integer, Field::Long, 11)));
    }

    public function testTextsReadsTheTextOfEachArgument(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));
        $integer = new Domain(Kind::Integer, Field::LongLong, 20);

        self::assertSame(['ab', '12'], (new Strings())->texts($frame, [new Constant($text, 'ab'), new Constant($integer, 12)]));
        self::assertSame([], (new Strings())->texts($frame, []));
    }

    public function testTextsAnswersNullWhenAnArgumentIsNull(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertNull((new Strings())->texts($frame, [new Constant($text, 'ab'), new Constant($text, null)]));
    }

    public function testNumberReadsAnArgumentAsAnInteger(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(5, (new Strings())->number($frame, new Constant($text, '5')));
        self::assertNull((new Strings())->number($frame, new Constant($text, null)));
    }

    public function testBytesTellsWhetherTheCharacterSetHasSingleByteCharacters(): void
    {
        $strings = new Strings();

        self::assertTrue($strings->bytes(Domain::string(4, Collation::known('latin1_swedish_ci'))));
        self::assertTrue($strings->bytes(Domain::string(4, Collation::binary())));
        self::assertFalse($strings->bytes(Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'))));
    }

    public function testCharactersSplitsTheTextByTheCharacterSet(): void
    {
        $strings = new Strings();
        $utf8 = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));
        $latin1 = Domain::string(4, Collation::known('latin1_swedish_ci'));

        self::assertSame(['h', 'é'], $strings->characters('hé', $utf8));
        self::assertSame(['h', "\xC3", "\xA9"], $strings->characters('hé', $latin1));
        self::assertSame([], $strings->characters('', $utf8));
    }

    public function testCharactersSplitsInvalidUtf8IntoBytes(): void
    {
        self::assertSame(["\xFF", "\xFE"], (new Strings())->characters("\xFF\xFE", Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'))));
    }

    public function testConcatJoinsTheArguments(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONCAT('My', 'S', 'QL'), CONCAT('My', NULL, 'QL'), CONCAT(14.3)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['MySQL', null, '14.3']], $result->rows);
        self::assertSame([Field::VarString, 20], [$result->columns[0]->type, $result->columns[0]->length]);
    }

    public function testConcatWsSkipsNullArguments(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONCAT_WS(',', 'First name', 'Second name', 'Last Name'), CONCAT_WS(',', 'First name', NULL, 'Last Name'), CONCAT_WS('-', 1, 2.5)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['First name,Second name,Last Name', 'First name,Last Name', '1-2.5']], $result->rows);
    }

    public function testConcatWsAnswersNullForANullSeparator(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONCAT_WS(NULL, 'a', 'b')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testUpperConvertsTheTextToUpperCase(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UPPER('Hej'), UCASE('abc'), UPPER('é'), UPPER(_latin1 'abc'), UPPER(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['HEJ', 'ABC', 'É', 'ABC', null]], $result->rows);
    }

    public function testUpperLeavesABinaryStringUnchanged(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UPPER(BINARY 'abc')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc']], $result->rows);
        self::assertTrue($result->columns[0]->binary());
    }

    public function testLowerConvertsTheTextToLowerCase(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LOWER('QUADRATICALLY'), LCASE('ABC'), LOWER('ÀBC'), LOWER(_latin1 'ABC'), LOWER(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['quadratically', 'abc', 'àbc', 'abc', null]], $result->rows);
    }

    public function testLowerLeavesABinaryStringUnchanged(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LOWER(BINARY 'ABC')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ABC']], $result->rows);
    }

    public function testLeftTakesTheLeftmostCharacters(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LEFT('foobarbar', 5), LEFT('héllo', 2), LEFT('abc', 9), LEFT('abc', 0), LEFT('abc', -1), LEFT(NULL, 1), LEFT('abc', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['fooba', 'hé', 'abc', '', '', null, null]], $result->rows);
    }

    public function testRightTakesTheRightmostCharacters(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT RIGHT('foobarbar', 4), RIGHT('héllo', 4), RIGHT('abc', 9), RIGHT('abc', 0), RIGHT('abc', -1), RIGHT(NULL, 1)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['rbar', 'éllo', 'abc', '', '', null]], $result->rows);
    }

    public function testSubstringTakesCharactersFromAPosition(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SUBSTRING('Quadratically', 5), SUBSTRING('foobarbar' FROM 4), SUBSTRING('Quadratically', 5, 6), SUBSTR('héllo', 2, 2), MID('abcdef', 2, 3)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ratically', 'barbar', 'ratica', 'él', 'bcd']], $result->rows);
    }

    public function testSubstringCountsANegativePositionFromTheEnd(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SUBSTRING('Sakila', -3), SUBSTRING('Sakila', -5, 3), SUBSTRING('Sakila' FROM -4 FOR 2), SUBSTRING('abc', -4)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ila', 'aki', 'ki', '']], $result->rows);
    }

    public function testSubstringAnswersAnEmptyStringOutsideTheText(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SUBSTRING('abc', 0), SUBSTRING('abc', 5), SUBSTRING('abc', 1, 0), SUBSTRING('abc', 1, -1), SUBSTRING(NULL, 1), SUBSTRING('abc', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['', '', '', '', null, null]], $result->rows);
    }

    public function testReplaceReplacesEveryOccurrenceCaseSensitively(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT REPLACE('www.mysql.com', 'w', 'Ww'), REPLACE('aBc', 'b', 'x'), REPLACE('abc', '', 'x'), REPLACE('abc', 'b', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['WwWwWw.mysql.com', 'aBc', 'abc', null]], $result->rows);
    }

    public function testReverseReversesTheCharacters(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT REVERSE('abc'), REVERSE('héllo'), REVERSE(''), REVERSE(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['cba', 'olléh', '', null]], $result->rows);
    }

    public function testRepeatRepeatsTheText(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT REPEAT('MySQL', 3), REPEAT('a', 0), REPEAT('a', -1), REPEAT(NULL, 2), REPEAT('a', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['MySQLMySQLMySQL', '', '', null, null]], $result->rows);
    }

    public function testRepeatAnswersNullForAResultLongerThanTheMaximumPacket(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT REPEAT('a', 67108865)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testPadPadsTheTextToALength(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LPAD('hi', 4, '??'), LPAD('hi', 5, 'ab'), RPAD('hi', 5, '?'), RPAD('hi', 6, 'xy'), LPAD('é', 3, 'ü')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['??hi', 'abahi', 'hi???', 'hixyxy', 'üüé']], $result->rows);
    }

    public function testPadCutsTheTextToALength(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LPAD('hi', 1, '??'), RPAD('hi', 1, '?'), LPAD('héllo', 2, 'x'), RPAD('abc', 0, 'x')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['h', 'h', 'hé', '']], $result->rows);
    }

    public function testPadAnswersNullForANegativeLengthOrANullArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LPAD('hi', -1, 'x'), RPAD(NULL, 3, 'x'), LPAD('hi', NULL, 'x'), RPAD('hi', 3, NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null, null]], $result->rows);
    }

    public function testStripRemovesLeadingOrTrailingSpaces(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LTRIM('  barbar'), RTRIM('barbar   '), CONCAT('[', LTRIM('  a  '), ']'), CONCAT('[', RTRIM('  a  '), ']'), LTRIM(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['barbar', 'barbar', '[a  ]', '[  a]', null]], $result->rows);
    }

    public function testSpaceAnswersANumberOfSpaces(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT SPACE(6), SPACE(0), SPACE(-1), SPACE(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['      ', '', '', null]], $result->rows);
    }

    public function testHexWritesTheBytesOfAString(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX('abc'), HEX('1'), HEX(''), HEX(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['616263', '31', '', null]], $result->rows);
    }

    public function testHexWritesTheDigitsOfANumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT HEX(255), HEX(0), HEX(-1), HEX(1.5)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['FF', '0', 'FFFFFFFFFFFFFFFF', '2']], $result->rows);
    }

    public function testUnhexReadsHexadecimalDigitsAsBytes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UNHEX('4D7953514C'), HEX(UNHEX('616263')), HEX(UNHEX('F')), UNHEX('')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['MySQL', '616263', '0F', '']], $result->rows);
        self::assertTrue($result->columns[0]->binary());
    }

    public function testUnhexAnswersNullForAStringThatIsNotHexadecimal(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UNHEX('GG'), UNHEX('4X'), UNHEX(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
    }

    public function testSubstringIndexTakesTheTextAroundADelimiter(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SUBSTRING_INDEX('www.mysql.com', '.', 2), SUBSTRING_INDEX('www.mysql.com', '.', -2), SUBSTRING_INDEX('a.b', '.', 5), SUBSTRING_INDEX('a.b', '.', -5)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['www.mysql', 'mysql.com', 'a.b', 'a.b']], $result->rows);
    }

    public function testSubstringIndexAnswersAnEmptyStringForAZeroCountOrAnEmptyDelimiter(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SUBSTRING_INDEX('a.b', '.', 0), SUBSTRING_INDEX('a.b', '', 1), SUBSTRING_INDEX(NULL, '.', 1)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['', '', null]], $result->rows);
    }

    public function testInsertReplacesCharactersFromAPosition(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT INSERT('Quadratic', 3, 4, 'What'), INSERT('Quadratic', -1, 4, 'What'), INSERT('Quadratic', 3, 100, 'What'), INSERT('Quadratic', 3, -1, 'What'), INSERT('héllo', 2, 1, 'e')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['QuWhattic', 'Quadratic', 'QuWhat', 'QuWhat', 'hello']], $result->rows);
    }

    public function testInsertAnswersNullForANullArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT INSERT(NULL, 1, 1, 'x'), INSERT('abc', NULL, 1, 'x'), INSERT('abc', 1, 1, NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
    }
}
