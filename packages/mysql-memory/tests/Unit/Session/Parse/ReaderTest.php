<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Reader;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Reader::class)]
#[Small]
final class ReaderTest extends TestCase
{
    public function testOperandKeepsTheDebugEntryBeforeDateInterpretation(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);

        $session->query("SHOW PARSE_TREE SELECT DATE 'bad'");
    }

    public function testTreeRefusesAStandalonePartitionBeforeItsParameterMarker(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $this->expectExceptionMessage("Partitioning can not be used stand-alone in query near 'PARTITION BY HASH(?)' at line 1");

        (new Reader())->tree('PARTITION BY HASH(?)', false, $session);
    }

    public function testRefusedPreservesThePartitionEntryBeforeALaterLexicalFailure(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = "PARTITION BY HASH('unclosed";
        $error = (new Reader())->refused(new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, 'later failure'), $sql, false, $session);

        self::assertSame("Partitioning can not be used stand-alone in query near '" . $sql . "' at line 1", $error->getMessage());
        self::assertSame([['Error', 1064, $error->getMessage()]], $session->diagnostics->conditions);
    }

    public function testBoundTypesAMarkerOfAPreparedStatementAsAVarchar(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ? + ?');
        $unbound = Resolved::string(16383, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertEquals([0 => $unbound, 1 => $unbound], (new Reader())->bound($tree, [], true, $session));
    }

    public function testBoundTypesAMarkerByItsValue(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ? + ?');

        self::assertEquals([0 => Domain::integer()->resolved()], (new Reader())->bound($tree, [[1, Domain::integer()]], false, $session));
        self::assertSame([], (new Reader())->bound($tree, [], false, $session));
    }

    public function testRefusedRecordsAParameterMarkerBeforeALaterSyntaxError(): void
    {
        $session = (new Instance('8.4.7'))->connect();

        $error = (new Reader())->refused(new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, 'x'), 'SELECT ? FROM', false, $session);

        self::assertSame("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near '? FROM' at line 1", $error->getMessage());
        self::assertSame([['Error', 1064, $error->getMessage()]], $session->diagnostics->conditions);
    }

    public function testReadRecordsTheHintCommentsAndRefusesAParameterMarkerOutsideAPreparedStatement(): void
    {
        $session = (new Instance())->connect();
        (new Reader())->read('SELECT /*+ BKA() */ ?', true, $session);

        self::assertTrue($session->commented);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);

        (new Reader())->read('SELECT ?', false, $session);
    }

    public function testRefusedCountsFollowingErrorsBeyondTheConditionStorageLimit(): void
    {
        $session = (new Instance())->connect();
        $session->diagnostics->limit = 1;
        $error = new SqlError(\MySqlMemory\Error\Family\AdministrationError::UnknownSystemVariable, 'unknown', following: [[1193, 'following']]);

        self::assertSame($error, (new Reader())->refused($error, 'SET x=(', true, $session));
        self::assertSame([['Error', 1193, 'unknown']], $session->diagnostics->conditions);
        self::assertSame([2, 2], [$session->diagnostics->raised(), $session->diagnostics->errors()]);
    }

    public function testRefusalAnswersTheParseErrorOfARefusedStatement(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT 1');

        self::assertSame(1064, (new Reader())->refusal($tree, new AnalysisException('refused'), 'SELECT 1', '', $session)->getCode());
    }

    public function testRejectionRetainsTheTypedProblemAndPrecedingNotices(): void
    {
        $session = (new Instance())->connect();
        $session->variables->database = 'd';
        $sql = "CREATE PROCEDURE p() BEGIN SELECT BINARY 1; SIGNAL SQLSTATE '00000'; END";
        $failure = new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::BadSqlState, '00000'), strlen($sql) - 5);

        $error = (new Reader())->rejection($failure, $sql, $session);

        self::assertSame(1407, $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame([1287, 1407], array_column($session->diagnostics->conditions, 1));
    }

    public function testReadDefersTemporalValidationInADebugInspection(): void
    {
        $session = (new Instance())->connect();

        $tree = (new Reader())->read("SHOW PARSE_TREE SELECT DATE 'bad'", false, $session);

        self::assertSame('start_entry', $tree->name);
        self::assertSame([], $session->diagnostics->conditions);
    }
}
