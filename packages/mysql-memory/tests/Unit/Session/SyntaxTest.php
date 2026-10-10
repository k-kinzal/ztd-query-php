<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Syntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(Syntax::class)]
#[Small]
final class SyntaxTest extends TestCase
{
    public function testDebugOnlyRefusesShowParseTreeAtParseTree(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'parse_tree   select 1' at line 1");

        $session->query('show   parse_tree   select 1');
    }

    public function testDebugOnlyAcceptsParseTreeAsAName(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT 1 AS parse_tree');
        (new Syntax())->debugOnly($tree, 'SELECT 1 AS parse_tree');
        $result = $session->query('SELECT 1 AS parse_tree')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame('parse_tree', $result->columns[0]->name);
    }

    public function testMarkersRefusesAParameterMarker(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT 1 + ?');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near '?' at line 1");

        (new Syntax())->markers($tree, 'SELECT 1 + ?');
    }

    public function testMarkersNamesTheLineOfTheMarker(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse("SELECT 1,\n?, 2");

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("near '?, 2' at line 2");

        (new Syntax())->markers($tree, "SELECT 1,\n?, 2");
    }

    public function testMarkersAcceptsAQuestionMarkInAString(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse("SELECT '?'");
        (new Syntax())->markers($tree, "SELECT '?'");
        $result = $session->query("SELECT '?'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['?']], $result->rows);
    }

    public function testErrorNamesTheTextFromTheRefusedToken(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'WHERE' at line 2");

        $session->query("SELECT 1 FROM\nWHERE");
    }

    public function testErrorCutsTheTextAt80Bytes(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("near 'SELEC " . str_repeat('x', 74) . "' at line 1");

        $session->query('SELEC ' . str_repeat('x', 100));
    }

    public function testNearOmitsTrailingWhitespaceButKeepsCommentsAndFollowingStatements(): void
    {
        $error = (new Syntax())->near(0, 'SELEC 1 /* comment */; ', "\nSELECT 2 \t\n", new RuntimeException());

        self::assertStringContainsString("near 'SELEC 1 /* comment */; \nSELECT 2' at line 1", $error->getMessage());
    }

    public function testErrorKeepsTheFailureItReports(): void
    {
        $failure = new RuntimeException('not a syntax error');
        $error = (new Syntax())->error($failure, 'SELECT');

        self::assertSame(1064, $error->getCode());
        self::assertSame('42000', $error->sqlState());
        self::assertSame($failure, $error->getPrevious());
    }

    public function testTemporalsRefusesATemporalLiteralBeforeNamesAreResolved(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect TIME value: '25:61:00'");

        $session->query("SELECT TIME '25:61:00' FROM nowhere");
    }

    public function testTemporalsFollowsTheZeroDateModes(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse("SELECT DATE '0000-00-00'");

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect DATE value: '0000-00-00'");

        (new Syntax())->temporals($tree, new SqlModes(['NO_ZERO_DATE']));
    }

    public function testUnquoteReadsDoubledQuotesAndEscapeSequences(): void
    {
        self::assertSame(["it's", 'a\\tb', 'a\\%', 'x'], [(new Syntax())->unquote("'it''s'", true), (new Syntax())->unquote('"a\\tb"', false), (new Syntax())->unquote("'a\\\\%'", true), (new Syntax())->unquote('x', true)]);
    }

    public function testDotsAnswerTheLeadingDotsOfAStatementByOffset(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $tree = $session->semantics()->parser()->parse('SELECT .t.a FROM .t');

        self::assertSame([7 => Deprecated::DotColumn, 17 => Deprecated::DotTable], (new Syntax())->dots($tree));
    }

    public function testInternalRefusesTheGeneratedColumnStatementOf57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("near 'PARSE_GCOL_EXPR (1)' at line 1");

        $session->query('PARSE_GCOL_EXPR (1)');
    }

    public function testErrorRefusesAnAttributeAGeneratedColumnCannotHave(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of DEFAULT and generated column');

        $session->query('CREATE TABLE t1 (a INT, b INT AS (a) DEFAULT 1)');
    }

    public function testUndeclaredRefusesAnIntoVariableBeforeTheUnionInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: v');

        $session->query('SELECT 1 INTO v FROM DUAL UNION SELECT 2 INTO @w');
    }

    public function testInternalRefusesAPartitioningClauseSentAloneInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("Partitioning can not be used stand-alone in query near 'PARTITION BY HASH (a) PARTITIONS 2' at line 1");

        $session->query('PARTITION BY HASH (a) PARTITIONS 2');
    }

    public function testPurgedRefusesASubqueryInTheMomentOfPurgeInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near '  SELECT 1)' at line 1");

        $session->query('PURGE BINARY LOGS BEFORE 1 + (  SELECT 1)');
    }

    public function testPurgedRefusesASubqueryNearItsParenthesisAfterAllInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("near '( SELECT 1) )' at line 1");

        $session->query('PURGE MASTER LOGS BEFORE (1 = ALL ( SELECT 1) )');
    }

    public function testIdentifierRemovesTheBackquotes(): void
    {
        self::assertSame(['a`b', 'c'], [(new Syntax())->identifier('`a``b`'), (new Syntax())->identifier('c')]);
    }

    public function testTemporalsQuotesTheFirst128BytesOfTheText(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect DATE value: '" . str_repeat('x', 128) . "'");

        $session->query("SELECT DATE '" . str_repeat('x', 600) . "'");
    }

    public function testErrorReportsTheTextAfterWithInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("near 'x' at line 1");

        $session->query('SELECT 1 WITH x');
    }

    public function testPrematureRefusesAParameterMarkerBeforeASyntaxErrorInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("near '? FROM' at line 1");

        $session->query('SELECT ? FROM');
    }

    public function testVariableAnswersAnUndeclaredIntoVariableBeforeAnOffset(): void
    {
        $tree = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->parser()->parse("SELECT 1 INTO nov FROM t WHERE DATE 'x'");

        self::assertSame(['nov', null], [(new Syntax())->variable($tree, 40, null), (new Syntax())->variable($tree, 10, null)]);
    }

    public function testNearQuotesTheTextFromAnOffsetWithTheStatementsAfter(): void
    {
        $error = (new Syntax())->near(8, "\nSELECT\n?;", ' SELECT 1', new RuntimeException('cause'));

        self::assertSame("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near '?; SELECT 1' at line 2", $error->getMessage());
    }

    public function testMarkersQuotesTheStatementsAfterTheMarker(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ?;');

        $this->expectExceptionMessage("near '?; SELECT 1' at line 1");

        (new Syntax())->markers($tree, 'SELECT ?;', ' SELECT 1');
    }

    public function testErrorQuotesAnUnterminatedStringFromItsQuote(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionMessage("near ''abc' at line 1");

        $session->query("SELECT 'abc");
    }

    public function testPrematureRefusesAMarkerBeforeASyntaxErrorInMySql84(): void
    {
        $session = (new Instance('8.4.7'))->connect();

        $this->expectExceptionMessage("near '? FROM' at line 1");

        $session->query('SELECT ? FROM');
    }
}
