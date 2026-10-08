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

        self::assertSame(['CONCAT', 'CONCAT_WS', 'REPLACE', 'REVERSE', 'REPEAT', 'LPAD', 'RPAD', 'LTRIM', 'RTRIM', 'SPACE', 'HEX', 'UNHEX'], $names);
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

        self::assertSame(['ab', '12'], (new Strings())->texts($frame, [new Constant($text, 'ab'), new Constant($integer, 12)], $text));
        self::assertSame([], (new Strings())->texts($frame, [], $text));
    }

    public function testTextsAnswersNullWhenAnArgumentIsNull(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertNull((new Strings())->texts($frame, [new Constant($text, 'ab'), new Constant($text, null)], $text));
    }

    public function testNumberReadsAnArgumentAsAnInteger(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(5, (new Strings())->number($frame, new Constant($text, '5')));
        self::assertNull((new Strings())->number($frame, new Constant($text, null)));
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
        $result = $session->query("SELECT LENGTH(REPEAT('a', 67108865)), REPEAT('a', 18446744073709551615)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[null, null]], $result->rows);
        self::assertSame([
            ['Warning', '1301', 'Result of repeat() was larger than max_allowed_packet (67108864) - truncated'],
            ['Warning', '1301', 'Result of repeat() was larger than max_allowed_packet (67108864) - truncated'],
        ], $warnings->rows);
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

    public function testFitsWarnsForEachResultPastMaxAllowedPacket(): void
    {
        $instance = new Instance();
        $instance->connect()->query('SET GLOBAL max_allowed_packet = 1024');
        $session = $instance->connect();
        $result = $session->query("SELECT CONCAT(REPEAT('a', 1000), REPEAT('b', 25)), LENGTH(CONCAT(REPEAT('a', 1000), REPEAT('b', 24))), CONCAT_WS(',', REPEAT('a', 1024)), LENGTH(CONCAT_WS('', REPEAT('a', 1024), NULL)), LPAD('a', 257, 'b'), LENGTH(LPAD(_latin1'a', 1024, _latin1'b')), RPAD('a', 257, 'b'), SPACE(1025), INSERT(REPEAT('a', 1024), 2, 0, 'b'), REPLACE(REPEAT('a', 1000), 'a', 'bb'), LENGTH(REPLACE(REPEAT('a', 1024), 'b', 'cc'))")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[null, '1024', null, '1024', null, '1024', null, null, null, null, '1024']], $result->rows);
        self::assertSame([
            ['Warning', '1301', 'Result of concat() was larger than max_allowed_packet (1024) - truncated'],
            ['Warning', '1301', 'Result of concat_ws() was larger than max_allowed_packet (1024) - truncated'],
            ['Warning', '1301', 'Result of lpad() was larger than max_allowed_packet (1024) - truncated'],
            ['Warning', '1301', 'Result of rpad() was larger than max_allowed_packet (1024) - truncated'],
            ['Warning', '1301', 'Result of space() was larger than max_allowed_packet (1024) - truncated'],
            ['Warning', '1301', 'Result of insert() was larger than max_allowed_packet (1024) - truncated'],
            ['Warning', '1301', 'Result of replace() was larger than max_allowed_packet (1024) - truncated'],
        ], $warnings->rows);
    }

    public function testFitsComparesTheBytesWithMaxAllowedPacket(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame([true, false], [(new Strings())->fits($frame, 67108864, 'repeat'), (new Strings())->fits($frame, 67108865.0, 'repeat')]);
        self::assertSame(1, $frame->context->diagnostics->count());
    }

    public function testNumberReadsAnUnsignedBigintBeyondTheSignedRangeAsTheLargestInt(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LEFT('abc', 18446744073709551615), RIGHT('abc', 18446744073709551615), SUBSTRING('abc', 1, 18446744073709551615), SUBSTRING('abc', 18446744073709551615), INSERT('abc', 1, 18446744073709551615, 'x'), SUBSTRING_INDEX('a,b,c', ',', 18446744073709551615)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc', 'abc', 'abc', '', 'x', 'a,b,c']], $result->rows);
    }

    public function testTextConvertsAnArgumentIntoTheCharacterSetOfTheResult(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(CONCAT(CONVERT('é' USING latin1), 'é')), HEX(CONCAT(CONVERT('é' USING ucs2), 1)), HEX(LPAD(CONVERT('é' USING latin1), 3, 'ü'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['E9E9', '00E90031', 'FCFCE9']], $result->rows);
    }

    public function testCharsetAnswersTheCharacterSetOfTheTextOfAValue(): void
    {
        self::assertSame(['latin1', 'utf8mb4'], [(new Strings())->charset(Domain::string(1, Collation::known('latin1_swedish_ci')))->name, (new Strings())->charset(Domain::integer())->name]);
    }

    public function testCountCountsTheCharactersInTheCharacterSetOfADomain(): void
    {
        self::assertSame([2, 1, 2], [(new Strings())->count('hé', Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'))), (new Strings())->count("\x00\xE9", Domain::string(4, Collation::known('ucs2_general_ci'))), (new Strings())->count('é', Domain::string(4, Collation::known('latin1_swedish_ci')))]);
    }

    public function testSliceTakesCharactersInTheCharacterSetOfADomain(): void
    {
        self::assertSame(['é', "\x00b"], [(new Strings())->slice('héb', 1, 1, Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'))), (new Strings())->slice("\x00a\x00b", 1, null, Domain::string(4, Collation::known('ucs2_general_ci')))]);
    }

}
