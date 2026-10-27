<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use ArrayObject;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Placement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Placement::class)]
#[Small]
final class PlacementTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerCheckRefusesAnOptionOutOfPlace(): iterable
    {
        yield 'later set operand' => ['SELECT 1 UNION SELECT SQL_BUFFER_RESULT 2', 1234, "Incorrect usage/placement of 'SQL_BUFFER_RESULT'"];
        yield 'derived table' => ['SELECT * FROM (SELECT SQL_CALC_FOUND_ROWS 1) AS x', 1234, "Incorrect usage/placement of 'SQL_CALC_FOUND_ROWS'"];
        yield 'subquery' => ['SELECT (SELECT HIGH_PRIORITY 1)', 1234, "Incorrect usage/placement of 'HIGH_PRIORITY'"];
        yield 'common table' => ['WITH x AS (SELECT SQL_BUFFER_RESULT 1) SELECT * FROM x', 1234, "Incorrect usage/placement of 'SQL_BUFFER_RESULT'"];
        yield 'first option in order' => ['SELECT 1 UNION SELECT SQL_CALC_FOUND_ROWS SQL_BUFFER_RESULT HIGH_PRIORITY 2', 1234, "Incorrect usage/placement of 'HIGH_PRIORITY'"];
        yield 'all and distinct first' => ['SELECT 1 UNION SELECT SQL_BUFFER_RESULT ALL DISTINCT 2', 1221, 'Incorrect usage of ALL and DISTINCT'];
        yield 'all and distinctrow' => ['SELECT DISTINCTROW ALL 1', 1221, 'Incorrect usage of ALL and DISTINCT'];
    }

    #[DataProvider('providerCheckRefusesAnOptionOutOfPlace')]
    public function testCheckRefusesAnOptionOutOfPlace(string $sql, int $code, string $message): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);

        (new Placement())->check($session->analyze($sql));
    }

    public function testCheckAcceptsTheOptionsOfTheFirstQueryBlock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        (new Placement())->check($session->analyze('(SELECT SQL_BUFFER_RESULT SQL_CALC_FOUND_ROWS HIGH_PRIORITY 1) UNION (SELECT 2)'));
        (new Placement())->check($session->analyze('INSERT INTO t SELECT SQL_BUFFER_RESULT 1'));

        self::assertCount(1, $session->query('SELECT SQL_BUFFER_RESULT 1 UNION SELECT 2 ORDER BY 1 LIMIT 1'));
    }

    public function testCheckLeavesUnusedCommonTableQuantifiersUnprepared(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->query('WITH c AS (SELECT ALL DISTINCT 1) SELECT 2')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2']], $reply->rows);
    }

    public function testCheckLeavesUnusedCommonTableModifiersUnprepared(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->query('WITH c AS (SELECT SQL_BUFFER_RESULT 1) SELECT 2')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2']], $reply->rows);
    }

    public function testFirstAnswersTheFirstQueryBlockOfTheStatementOrOfTheRowsItWrites(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $query = $session->analyze('WITH x AS (SELECT 1) (SELECT 2 UNION SELECT 3) ORDER BY 1');
        $insert = $session->analyze('INSERT INTO t (SELECT 4 UNION SELECT 5)');

        self::assertSame((new Walker())->find($query, Select::class)[1], (new Placement())->first($query));
        self::assertSame((new Walker())->find($insert, Select::class)[0], (new Placement())->first($insert));
        self::assertNull((new Placement())->first($session->analyze('UPDATE t SET a = (SELECT 1)')));
    }

    public function testCachedRefusesAQueryCacheModifierOutsideTheFirstBlockIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1234);
        $this->expectExceptionMessage("Incorrect usage/placement of 'SQL_NO_CACHE'");

        (new Placement())->cached($session->analyze('SELECT 1 UNION SELECT SQL_NO_CACHE 1'), GrammarRelease::MySql5744);
    }

    public function testCachedTakesTheModifiersOfTheFirstBlock(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        (new Placement())->cached($session->analyze('SELECT SQL_CACHE 1 UNION SELECT 2'), GrammarRelease::MySql5744);
        $result = $session->query('SELECT SQL_CACHE 1 UNION SELECT 2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
    }

    public function testUnitedRefusesIntoInAUnionOperandButTheLastIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect usage of UNION and INTO');

        (new Placement())->united($session->analyze('(SELECT 1 INTO @a) UNION SELECT 2'), GrammarRelease::MySql5744);
    }

    public function testBlocksAnswersTheBlocksOfAQueryThroughParenthesesAndUnions(): void
    {
        $session = (new Instance())->connect();

        self::assertCount(3, (new Placement())->blocks($session->analyze('(SELECT 1) UNION (SELECT 2 UNION SELECT 3)')));
    }

    public function testMisplacedRefusesAQueryCacheModifierOfABlockButTheFirstIn56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $statement = $session->analyze('SELECT 1 UNION SELECT SQL_CACHE 2');
        $selects = (new Walker())->find($statement, Select::class);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1234);

        (new Placement())->misplaced($selects[1], $selects[0], GrammarRelease::MySql5651);
    }

    public function testBoundariesAnswerTheBlockAfterAUnionOperandWithInto(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $statement = $session->analyze('(SELECT 1 INTO @a) UNION SELECT 2');
        $selects = (new Walker())->find($statement, Select::class);

        self::assertSame([spl_object_id($selects[1])], (new Placement())->boundaries($statement, GrammarRelease::MySql5744));
    }

    public function testCheckRefusesAllWithDistinctBeforeAQueryCacheModifierThatIsNotLastIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect usage of ALL and DISTINCT');

        (new Placement())->check($session->analyze('SELECT 1 UNION SELECT SQL_CACHE ALL DISTINCT 2'), GrammarRelease::MySql5744);
    }

    public function testCarrierAnswersTheStatementExplainExplainsInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $statement = $session->analyze('EXPLAIN SELECT SQL_CACHE 1');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $statement);
        self::assertSame($statement->statement, (new Placement())->carrier($statement, GrammarRelease::MySql5651));
    }

    public function testCheckEndsEachBlockAfterTheBlocksWrittenInIt(): void
    {
        $session = (new Instance())->connect();
        $events = new ArrayObject();
        $statement = $session->analyze('SELECT (SELECT 1) FROM (SELECT 2) AS x UNION SELECT 3');
        $selects = (new Walker())->find($statement, Select::class);

        (new Placement())->check($statement, GrammarRelease::MySql847, static function (Select $select, bool $ended) use ($events): void {
            $events->append([$select, $ended]);
        });

        self::assertSame([[$selects[0], false], [$selects[1], false], [$selects[1], true], [$selects[2], false], [$selects[2], true], [$selects[0], true], [$selects[3], false], [$selects[3], true]], $events->getArrayCopy());
    }

    public function testExpressionBlocksPreservesTheDerivedTableBoundaryWithinAnExpression(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $statement = $session->analyze('SELECT (SELECT v FROM (SELECT 1 AS v) d UNION SELECT 2)');
        $blocks = (new Walker())->find($statement, Select::class);

        self::assertSame([spl_object_id($blocks[1]) => true, spl_object_id($blocks[3]) => true], (new Placement())->expressionBlocks($statement));
    }

    public function testCheckAcceptsCombinedQuantifiersInLegacyExpressionQueries(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $reply = $session->query('SELECT (SELECT ALL DISTINCT 1), EXISTS(SELECT ALL DISTINCT 2), 1 IN(SELECT ALL DISTINCT 1)')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['1', '1', '1']], $reply->rows);
    }

    public function testClosedEndsTheOpenBlocksThatDoNotHoldTheNextOne(): void
    {
        $session = (new Instance())->connect();
        $selects = (new Walker())->find($session->analyze('SELECT (SELECT 1) UNION SELECT 2'), Select::class);
        $ended = new ArrayObject();

        $open = (new Placement())->closed([$selects[0], $selects[1]], $selects[2], static function (Select $select) use ($ended): void {
            $ended->append($select);
        });

        self::assertSame([[], [$selects[1], $selects[0]]], [$open, $ended->getArrayCopy()]);
    }
}
