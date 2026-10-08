<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Measures;
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

#[CoversClass(Measures::class)]
#[Small]
final class MeasuresTest extends TestCase
{
    public function testRoutinesNamesTheStringFunctionsThatAnswerNumbers(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Measures())->routines());

        self::assertSame(['LENGTH', 'OCTET_LENGTH', 'BIT_LENGTH', 'CHAR_LENGTH', 'CHARACTER_LENGTH', 'ASCII', 'LOCATE', 'INSTR', 'STRCMP', 'FIELD', 'FIND_IN_SET'], $names);
    }

    public function testBytesCountsTheBytesOfTheText(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LENGTH('abc'), LENGTH('é'), OCTET_LENGTH('ab'), BIT_LENGTH('ab'), LENGTH(1.50), LENGTH(NULL), BIT_LENGTH(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '2', '2', '16', '4', null, null]], $result->rows);
        self::assertSame([Field::LongLong, 10], [$result->columns[0]->type, $result->columns[0]->length]);
    }

    public function testBytesReadsAnArgumentDirectly(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(6, (new Measures())->bytes($frame, new Constant($text, 'héllo')));
        self::assertNull((new Measures())->bytes($frame, new Constant($text, null)));
    }

    public function testCharactersCountsTheCharactersOfTheCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CHAR_LENGTH('é'), CHARACTER_LENGTH('ab'), CHAR_LENGTH(BINARY 'é'), CHAR_LENGTH(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2', '2', null]], $result->rows);
    }

    public function testCharactersReadsAnArgumentDirectly(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $utf8 = Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'));
        $latin1 = Domain::string(6, Collation::known('latin1_swedish_ci'));

        self::assertSame(5, (new Measures())->characters($frame, new Constant($utf8, 'héllo'), new Strings()));
        self::assertSame(6, (new Measures())->characters($frame, new Constant($latin1, 'héllo'), new Strings()));
    }

    public function testRoutinesAnswerTheCodeOfTheFirstByteForAscii(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ASCII('A'), ASCII('dx'), ASCII(''), ASCII(2), ASCII(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['65', '100', '0', '50', null]], $result->rows);
    }

    public function testLocateFindsTheFirstOccurrence(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LOCATE('bar', 'foobarbar'), LOCATE('xbar', 'foobar'), LOCATE('bar', 'foobarbar', 5), LOCATE('', 'abc'), LOCATE('a', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4', '0', '7', '1', null]], $result->rows);
    }

    public function testLocateAnswersZeroForAStartOutsideTheText(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LOCATE('a', 'banana', 0), LOCATE('a', 'banana', 99), LOCATE('a', 'banana', 3)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '4']], $result->rows);
    }

    public function testLocateComparesByCharactersInTheCollation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LOCATE('B', 'abc'), LOCATE('é', 'café'), LOCATE('B', BINARY 'abc')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '4', '0']], $result->rows);
    }

    public function testLocateTakesTheArgumentsOfInstrInTheOtherOrder(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT INSTR('foobarbar', 'bar'), INSTR('xbar', 'foobar'), INSTR('café', 'É')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4', '0', '4']], $result->rows);
    }

    public function testStrcmpComparesByTheCollation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT STRCMP('text', 'text2'), STRCMP('text2', 'text'), STRCMP('text', 'text'), STRCMP('a', 'A'), STRCMP(BINARY 'a', 'A'), STRCMP(NULL, 'a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-1', '1', '0', '0', '1', null]], $result->rows);
    }

    public function testFieldAnswersThePositionOfTheFirstEqualCandidate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT FIELD('Bb', 'Aa', 'Bb', 'Cc', 'Dd', 'Ff'), FIELD('Gg', 'Aa', 'Bb'), FIELD('b', 'a', NULL, 'B'), FIELD(2, 1, 2)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '0', '3', '2']], $result->rows);
    }

    public function testFieldAnswersZeroForNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT FIELD(NULL, 'a', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
    }

    public function testFindInSetAnswersThePositionInTheList(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT FIND_IN_SET('b', 'a,b,c,d'), FIND_IN_SET('x', 'a,b'), FIND_IN_SET('B', 'a,b'), FIND_IN_SET('a', '')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '0', '2', '0']], $result->rows);
    }

    public function testFindInSetAnswersNullForANullArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT FIND_IN_SET(NULL, 'a'), FIND_IN_SET('a', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
    }

    public function testLocateSearchesInTheCharacterSetOfTheCollation(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (c VARCHAR(10) CHARACTER SET latin1, u VARCHAR(10) CHARACTER SET utf16); INSERT INTO t VALUES ('é€', 'a2é2')");
        $result = $session->query("SELECT LOCATE('2', u), LOCATE('é', u, 3), INSTR(u, 'é2'), POSITION('€' IN c), FIND_IN_SET('x', CONVERT('a,x' USING latin1)), CHAR_LENGTH(u), LENGTH(u) FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '3', '3', '2', '2', '4', '8']], $result->rows);
    }
}
