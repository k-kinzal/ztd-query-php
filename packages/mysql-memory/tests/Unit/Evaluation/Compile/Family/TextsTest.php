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

    public function testSoundsLikeComparesTheCodesInTheCollationOfBoth(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'éb' SOUNDS LIKE 'eb', 'éb' COLLATE utf8mb4_bin SOUNDS LIKE 'Éb', 'ÄÖ' SOUNDS LIKE 'Ä'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1']], $result->rows);
    }

    public function testCharactersKeepsValidBytesAndRefusesInvalidOnesInAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $strict = $session->query('SELECT HEX(CHAR(0x41C3 USING utf8mb4)), HEX(CHAR(0x41C3 USING ascii)), HEX(CHAR(0x414243 USING utf16))')[0];
        $session->query("SET sql_mode = ''");
        $loose = $session->query('SELECT HEX(CHAR(0x41C3 USING utf8mb4))')[0];

        self::assertInstanceOf(ResultSet::class, $strict);
        self::assertInstanceOf(ResultSet::class, $loose);
        self::assertSame([[null, '41C3', '00414243']], $strict->rows);
        self::assertSame([['41']], $loose->rows);
    }

    public function testRegexpMatchesInTheCollationOfBothOperands(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'abc' REGEXP '^a', 'ABC' REGEXP 'b', 'abc' NOT REGEXP 'b', 'abc' REGEXP 'b' COLLATE utf8mb4_bin, 'a/b' REGEXP 'a/b', NULL REGEXP 'a'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '1', '1', null]], $result->rows);
    }

    public function testRegexpMatchesAsRegexpLikeFromMySql80(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'STRASSE' REGEXP 'straße', 'a\\nb' REGEXP '^b', 'abc' REGEXP '\\\\p{Lu}'")[0];
        $error = $session->run("SELECT 'abc' REGEXP 'a{2,1}'");

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1']], $result->rows);
        self::assertInstanceOf(SqlError::class, $error[0]);
        self::assertSame([3693, 'The maximum is less than the minumum in a {min,max} interval.'], [$error[0]->getCode(), $error[0]->getMessage()]);
    }

    public function testRegexpKeepsItsOwnMatchingInMySql57(): void
    {
        $result = (new Instance('5.7.44'))->connect()->query("SELECT 'STRASSE' REGEXP 'straße', 'abc' REGEXP 'B'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1']], $result->rows);
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

    public function testRegexpRefusesABinaryStringWithAStringOfAnotherCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $left = $session->run("SELECT X'41' REGEXP 'a'");
        $right = $session->run("SELECT 'a' REGEXP BINARY 'a'");
        $result = $session->query("SELECT X'31' REGEXP 1, B'1000001' REGEXP B'1000001'")[0];

        self::assertInstanceOf(SqlError::class, $left[0]);
        self::assertSame([3995, "Character set 'binary' cannot be used in conjunction with 'utf8mb4_0900_ai_ci' in call to regexp_like."], [$left[0]->getCode(), $left[0]->getMessage()]);
        self::assertInstanceOf(SqlError::class, $right[0]);
        self::assertSame("Character set 'utf8mb4_0900_ai_ci' cannot be used in conjunction with 'binary' in call to regexp_like.", $right[0]->getMessage());
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testWeightCompilesWeightString(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT WEIGHT_STRING(1) = 0x8000000000000001')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testMatchChecksTheCallAsTheServerDoesBeforeItSearches(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b TEXT, c TEXT, FULLTEXT (c))');
        $session->query('CREATE TABLE u (b TEXT)');
        $against = $session->run('SELECT MATCH(b) AGAINST (b) FROM t');
        $tables = $session->run("SELECT MATCH(t.b, u.b) AGAINST ('x') FROM t, u");
        $index = $session->run("SELECT MATCH(b) AGAINST ('x') FROM t");

        self::assertInstanceOf(SqlError::class, $against[0]);
        self::assertSame('Incorrect arguments to AGAINST', $against[0]->getMessage());
        self::assertInstanceOf(SqlError::class, $tables[0]);
        self::assertSame('Incorrect arguments to MATCH', $tables[0]->getMessage());
        self::assertInstanceOf(SqlError::class, $index[0]);
        self::assertSame([1191, "Can't find FULLTEXT index matching the column list"], [$index[0]->getCode(), $index[0]->getMessage()]);
    }

}
