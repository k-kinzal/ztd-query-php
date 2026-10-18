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
    public function testMarkerEndKeepsTheCompletedGrammarBoundary(): void
    {
        $reader = new Reader();
        $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral('bad', \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Time);
        $rejection = new \SqlSemantics\Construction\RejectedInput($problem, 17, true);
        self::assertSame(17, $reader->markerEnd($rejection, \SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertSame(PHP_INT_MAX, $reader->markerEnd($rejection, \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertSame(PHP_INT_MAX, $reader->markerEnd(null, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testReadKeepsAnUnpreparedMarkerBeforeTemporalInterpretation(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near '?' at line 1");
        (new Reader())->read("SELECT TIME 'bad', ?", false, $session);
    }

    public function testTargetNoticesKeepsGrammarFailuresBeforeModernTargetResolution(): void
    {
        $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2);
        $grammar = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem);
        $contextual = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
        $notices = [[1, $grammar], [2, $contextual]];

        self::assertSame([[1, $grammar]], (new Reader())->targetNotices($notices, true, false));
        self::assertSame([], (new Reader())->targetNotices($notices, true, true));
        self::assertSame($notices, (new Reader())->targetNotices($notices, false, true));
    }

    public function testRejectionFormatsAHashRefusalBeforeTheIncompleteSuffix(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run("ALTER USER u IDENTIFIED BY PASSWORD 'text' REQUIRE");

        self::assertSame([['Error', 1064, "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near ''text' REQUIRE' at line 1"]], $session->diagnostics->conditions);
    }

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

    public function testBoundRetainsTheLegacyNullMarkerDomain(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ?');
        $types = (new Reader())->bound($tree, [[null, Domain::null()]], true, $session);

        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null, Field::VarString, Coercibility::Coercible], [$types[0]->kind, $types[0]->field, $types[0]->coercibility]);
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

        self::assertSame([], $session->hinted);
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

    public function testRejectionKeepsOneDelayedWarningForACompleteRefusedWrite(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d');
        $session->run('REPLACE DELAYED t SELECT *');

        self::assertSame([['Warning', 3005, 'REPLACE DELAYED is no longer supported. The statement was converted to REPLACE.'], ['Error', 1146, "Table 'd.t' doesn't exist"]], $session->diagnostics->conditions);
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

    public function testReadRefusesASubqueryInTheMomentOfPurgeInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near '  SELECT 1)' at line 1");

        $session->query('PURGE BINARY LOGS BEFORE 1 + (  SELECT 1)');
    }

    public function testReadRefusesASubqueryNearItsParenthesisAfterAllInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("near '( SELECT 1) )' at line 1");

        $session->query('PURGE MASTER LOGS BEFORE (1 = ALL ( SELECT 1) )');
    }


    public function testRejectionKeepsSyntaxConditionsBeforeDeleteTargetResolution(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 'c'");
        $session->query('WITH unused AS (WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1) DELETE FROM x USING t');
    }
    public function testTemplateLeavesIndependentOperandConditionsAtTheirOwnBoundary(): void
    {
        $session = (new Instance())->connect();
        $failure = new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2), 0);
        self::assertNull((new Reader())->template($failure, 'SELECT ROW(1,2)', $session));
    }

}
