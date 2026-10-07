<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Syntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Syntax::class)]
#[Small]
final class SyntaxTest extends TestCase
{
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
}
