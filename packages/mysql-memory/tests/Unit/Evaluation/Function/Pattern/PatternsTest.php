<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Pattern\Patterns;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Patterns::class)]
#[Small]
final class PatternsTest extends TestCase
{
    public function testRoutinesNamesTheRegularExpressionFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Patterns())->routines());

        self::assertSame(['REGEXP_LIKE', 'REGEXP_INSTR', 'REGEXP_SUBSTR', 'REGEXP_REPLACE'], $names);
    }

    public function testResolveLikeRefusesABinaryPatternForAStringColumnEvenWithoutRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a VARCHAR(10))');
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Character set 'utf8mb4_0900_ai_ci' cannot be used in conjunction with 'binary' in call to regexp_like.");

        $session->query("SELECT REGEXP_LIKE(a, _binary'x') FROM t");
    }

    public function testResolveInstrRefusesAKnownReturnOptionEvenWhenTheCallIsNotEvaluated(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to regexp_instr: return_option must be 1 or 0.');

        (new Instance())->connect()->query("SELECT IF(1, 1, REGEXP_INSTR('a', 'a', 1, 1, 5))");
    }

    public function testResolveSubstrRefusesABinarySubjectWithAStringPattern(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Character set 'binary' cannot be used in conjunction with 'utf8mb4_0900_ai_ci' in call to regexp_substr.");

        (new Instance())->connect()->query("SELECT REGEXP_SUBSTR(_binary'abc', 'b')");
    }

    public function testResolveReplaceChecksTheReplacementToo(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Character set 'binary' cannot be used in conjunction with 'utf8mb4_0900_ai_ci' in call to regexp_replace.");

        (new Instance())->connect()->query("SELECT REGEXP_REPLACE(_binary'abc', _binary'b', 'x')");
    }

    public function testCheckAllowsValuesThatAreNoStrings(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_LIKE(123, 2), REGEXP_LIKE(1.50, '50'), REGEXP_INSTR(1.50, '5'), REGEXP_SUBSTR(DATE'2020-01-02', '-..-')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '3', '-01-']], $result->rows);
    }

    public function testOptionRefusesAReturnOptionOtherThanZeroOrOne(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to regexp_instr: return_option must be 1 or 0.');

        (new Instance())->connect()->query("SELECT REGEXP_INSTR(NULL, 'a', 1, 1, 1.5)");
    }

    public function testCollationIgnoresCaseInACaseInsensitiveCollation(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_LIKE('ABC', 'abc'), REGEXP_LIKE('ABC' COLLATE utf8mb4_bin, 'abc'), REGEXP_LIKE('a', 'A' COLLATE utf8mb4_0900_as_cs), REGEXP_LIKE('É', 'é'), REGEXP_LIKE('é', 'e')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '0', '1', '0']], $result->rows);
    }

    public function testTextReadsABinaryStringByteByByte(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_INSTR(_binary'éa', _binary'a'), HEX(REGEXP_SUBSTR(_binary'éa', _binary'.')), REGEXP_LIKE(_binary'ABC', _binary'abc'), REGEXP_LIKE(_binary'ABC', _binary'abc', 'i'), REGEXP_LIKE(_binary 0x80, _binary '\\\\x{20ac}')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', 'C3', '0', '1', '1']], $result->rows);
    }

    public function testOutputWritesTheResultInItsCharacterSet(): void
    {
        $result = (new Instance())->connect()->query("SELECT HEX(REGEXP_REPLACE(_binary 0xff41, _binary'A', _binary'B')), HEX(REGEXP_REPLACE(_latin1 'A', _latin1 'A', 'ā')), HEX(REGEXP_REPLACE(_latin1 'A', _latin1 'A', '€'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['FF42', '3F', '80']], $result->rows);
    }

    public function testIntegerWarnsForATruncatedNumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT REGEXP_INSTR('abc', 'b', '2x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[['2']], [['Warning', '1292', "Truncated incorrect INTEGER value: '2x'"]]], [$result->rows, $warnings->rows]);
    }

    public function testModeReadsTheMatchType(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_LIKE('a\\nb', 'a.b'), REGEXP_LIKE('a\\nb', 'a.b', 'n'), REGEXP_LIKE('a\\nb', '^b', 'm'), REGEXP_LIKE('a\\rb', 'a\$', 'mu'), REGEXP_LIKE('ABC', 'abc', 'ic'), REGEXP_LIKE('a', 'a', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '1', '0', '0', null]], $result->rows);
    }

    public function testModeRefusesAnUnknownMatchType(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to regexp_like');

        (new Instance())->connect()->query("SELECT REGEXP_LIKE(NULL, 'a', 'x')");
    }

    public function testExpressionRaisesThePatternErrorEvenForANullSubject(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Mismatched parenthesis in regular expression.');

        (new Instance())->connect()->query("SELECT REGEXP_LIKE(NULL, '(')");
    }

    public function testLocatedNotesTheDefaultLocale(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT REGEXP_LIKE('a', '\\\\X')");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '4077', 'Regular expression library used default (root) locale.']], $warnings->rows);
    }

    public function testOffsetCountsCharacters(): void
    {
        self::assertSame([0, 4, 5], [(new Patterns())->offset('😀é', 0), (new Patterns())->offset('😀é', 1), (new Patterns())->offset('a😀', 9)]);
    }

    public function testLikeTellsWhetherThePatternMatches(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_LIKE('STRASSE', 'straße'), REGEXP_LIKE('ss', '[ß]'), REGEXP_LIKE('a', '\\\\p{Lu}'), REGEXP_LIKE(NULL, 'a'), REGEXP_LIKE('a', NULL), 'abc' REGEXP 'B'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', null, null, '1']], $result->rows);
    }

    public function testInstrAnswersThePositionOfAnOccurrence(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_INSTR('abcabc', 'b', 3), REGEXP_INSTR('abcabc', 'b', 1, 2), REGEXP_INSTR('abcabc', 'bc', 1, 2, 1), REGEXP_INSTR('abcabc', 'b', 1, 3), REGEXP_INSTR('😀a😀b', 'b'), REGEXP_INSTR('abc', '^b', 2), REGEXP_INSTR('abc', '(?<=a)b', 2), REGEXP_INSTR('', '^\$', -1)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', '5', '7', '0', '4', '2', '0', '-1']], $result->rows);
    }

    public function testInstrRefusesAPositionPastTheLastCharacter(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Index out of bounds in regular expression search.');

        (new Instance())->connect()->query("SELECT REGEXP_INSTR('abc', 'b', 4)");
    }

    public function testSubstrAnswersTheTextOfAnOccurrence(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_SUBSTR('abc def ghi', '[a-z]+', 1, 3), REGEXP_SUBSTR('abc', 'b', 4), REGEXP_SUBSTR('', 'x*'), REGEXP_SUBSTR('abc', '^b', 2), REGEXP_SUBSTR('abc', '(?<=a)b', 2), REGEXP_SUBSTR('😀é', '.', 2)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ghi', null, '', null, 'b', 'é']], $result->rows);
    }

    public function testSubstrRefusesAPositionBelowOne(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect parameters in the call to native function 'regexp_substr'");

        (new Instance())->connect()->query("SELECT REGEXP_SUBSTR(NULL, 'a', 0)");
    }

    public function testReplaceReplacesEveryOccurrenceOrOne(): void
    {
        $result = (new Instance())->connect()->query("SELECT REGEXP_REPLACE('abc', 'x*|b', '-'), REGEXP_REPLACE('abc', 'b*', '-'), REGEXP_REPLACE('abcabc', 'b', '-', 1, 2), REGEXP_REPLACE('abcabc', 'b', '-', 1, -1), REGEXP_REPLACE('abc', '(?<=a)b', 'X', 2), REGEXP_REPLACE('abc', '(b)', '[\$1\\\\\$1]'), REGEXP_REPLACE('', '^\$', '-')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-a-b-c-', '-a--c-', 'abca-c', 'a-cabc', 'aXc', 'a[b$1]c', '']], $result->rows);
    }

    public function testReplaceRefusesAGroupThePatternLacks(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Index out of bounds in regular expression search.');

        (new Instance())->connect()->query("SELECT REGEXP_REPLACE('abc', '(b)', '[\$2]')");
    }

    public function testSearchChecksThePositionBeforeTheSubject(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect parameters in the call to native function 'regexp_replace'");

        (new Instance())->connect()->query("SELECT REGEXP_REPLACE(NULL, 'b', NULL, 0)");
    }
}
