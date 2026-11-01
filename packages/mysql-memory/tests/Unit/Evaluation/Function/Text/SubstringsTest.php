<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Function\Text\Substrings;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Substrings::class)]
#[Small]
final class SubstringsTest extends TestCase
{
    public function testRoutinesNamesTheFunctionsThatTakeOrReplaceAPartOfAString(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Substrings())->routines());

        self::assertSame(['LEFT', 'RIGHT', 'SUBSTRING', 'SUBSTR', 'MID', 'SUBSTRING_INDEX', 'INSERT'], $names);
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
