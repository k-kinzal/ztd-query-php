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
}
