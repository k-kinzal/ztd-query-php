<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Texts;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Texts::class)]
#[Small]
final class TextsTest extends TestCase
{
    public function testCallEvaluatesTheInlineRoutineForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (s VARCHAR(10))');
        $session->query("INSERT INTO t VALUES ('xax'), ('xxbx'), (NULL)");
        $result = $session->query("SELECT TRIM(BOTH 'x' FROM s) FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['a'], ['b'], [null]], $result->rows);
    }

    public function testCollatedComparesInTheCollationWritten(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a' = 'A' COLLATE utf8mb4_bin, 'a' = 'A', 'abc' COLLATE utf8mb4_bin")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', 'abc']], $result->rows);
    }

    public function testCollatedRefusesAnUnknownCollation(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1273);
        $this->expectExceptionMessage("Unknown collation: 'nosuch'");

        $session->query("SELECT 'abc' COLLATE nosuch");
    }

    public function testCollatedRefusesACollationOfAnotherCharacterSet(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1253);
        $this->expectExceptionMessage("COLLATION 'latin1_swedish_ci' is not valid for CHARACTER SET 'utf8mb4'");

        $session->query("SELECT 'abc' COLLATE latin1_swedish_ci");
    }

    public function testBinaryComparesTheBytesOfTheValue(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT BINARY 'a' = 'A', HEX(BINARY 'é')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', 'C3A9']], $result->rows);
        self::assertSame(['Warning', 1287, "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead"], $session->diagnostics->conditions[0]);
    }

    public function testConvertConvertsToTheCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONVERT('abc' USING latin1), CONVERT('é' USING utf8mb4), CONVERT('abc' USING binary) = 'ABC'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc', 'é', '0']], $result->rows);
    }

    public function testConvertRefusesAnUnknownCharacterSet(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1115);
        $this->expectExceptionMessage("Unknown character set: 'nosuch'");

        $session->query("SELECT CONVERT('abc' USING nosuch)");
    }

    public function testTrimRemovesTheStringFromTheSidesWritten(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT TRIM('  bar   '), TRIM(LEADING 'x' FROM 'xxxbarxxx'), TRIM(BOTH 'x' FROM 'xxxbarxxx'), TRIM(TRAILING 'xyz' FROM 'barxxyz'), TRIM('' FROM 'ab'), TRIM(NULL FROM 'ab')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['bar', 'barxxx', 'bar', 'barx', 'ab', null]], $result->rows);
    }

    public function testPositionAnswersThePositionOfTheFirstOccurrence(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT POSITION('bar' IN 'foobarbar'), POSITION('xbar' IN 'foobar'), POSITION(NULL IN 'foobar')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4', '0', null]], $result->rows);
    }

    public function testCharWritesTheBytesOfEachIntegerAndSkipsNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CHAR(77,121,83,81,'76'), HEX(CHAR(256)), HEX(CHAR(1,0)), CHAR(65, NULL, 66), HEX(CHAR(0))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['MySQL', '0100', '0100', 'AB', '00']], $result->rows);
    }

    public function testCharGivesTheResultTheCharacterSetWritten(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CHAR(77,121,83,81,'76' USING utf8mb4), CHAR(65) = 'a', CHAR(65 USING utf8mb4) = 'a'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['MySQL', '0', '1']], $result->rows);
    }

    public function testSoundsLikeComparesTheSoundexCodes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'Robert' SOUNDS LIKE 'Rupert', 'a' SOUNDS LIKE 'b', NULL SOUNDS LIKE 'a'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', null]], $result->rows);
    }

    public function testSoundexAnswersTheFirstLetterAndTheDigitsOfTheConsonants(): void
    {
        self::assertSame(['H400', 'Q36324', 'R163', 'R163', ''], [Texts::soundex('Hello'), Texts::soundex('Quadratically'), Texts::soundex('Robert'), Texts::soundex('Rupert'), Texts::soundex('123')]);
    }

    public function testRegexpMatchesInTheCollationOfBothOperands(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'abc' REGEXP '^a', 'ABC' REGEXP 'b', 'abc' NOT REGEXP 'b', 'abc' REGEXP 'b' COLLATE utf8mb4_bin, 'a/b' REGEXP 'a/b', NULL REGEXP 'a'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '1', '1', null]], $result->rows);
    }

    public function testRegexpIsCaseSensitiveInABinaryCollation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'ABC' REGEXP 'b' COLLATE utf8mb4_bin, 'ABC' REGEXP 'B' COLLATE utf8mb4_bin")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1']], $result->rows);
    }

    public function testConvertHoldsTheBytesOfTheTargetCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(CONVERT('é' USING latin1)), LENGTH(CONVERT('é' USING latin1)), CHAR_LENGTH(CONVERT('é' USING latin1)), HEX(CONVERT('€' USING latin1)), HEX(CONVERT('中' USING latin1)), HEX(CONVERT(_latin1 X'E9' USING utf8mb4)), HEX(CONVERT('é' USING utf16)), HEX(CONVERT('é' USING binary)), HEX(_latin1'é')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['E9', '1', '1', '80', '3F', 'C3A9', '00E9', 'C3A9', 'C3A9']], $result->rows);
    }

    public function testTrimAndRegexpReadTheCharacterSetOfTheirArguments(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(TRIM(CONVERT(' é ' USING latin1))), HEX(TRIM(LEADING 'é' FROM CONVERT('ééa' USING latin1))), CONVERT('éa' USING latin1) REGEXP '^éa$', HEX(TRIM(CONVERT(' é ' USING utf16)))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['E9', '61', '1', '00E9']], $result->rows);
    }
}
