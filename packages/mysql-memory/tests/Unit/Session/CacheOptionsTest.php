<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\CacheOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Parser\SyntaxException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(CacheOptions::class)]
#[Small]
final class CacheOptionsTest extends TestCase
{
    public function testLegacyKeepsAParseErrorWhenNoEarlierConditionExists(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $error = new SqlError(StatementError::ParseError, 'unfinished');

        self::assertSame($error, (new CacheOptions())->legacy($error, 'SELECT', $session));
    }

    public function testReportedDoesNotReadBeyondAnAbortingSemanticInputRefusal(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $failure = new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused(), 9);
        $error = (new \MySqlMemory\Session\Parse\Entry\PartitionInput())->error($failure, "PARTITION BY HASH(DATE 'bad')", $session);

        self::assertSame($error, (new CacheOptions())->reported($error, "PARTITION BY HASH(DATE 'bad')", $session));
        $wrapped = new SqlError($error->error, $error->getMessage(), $error, recorded: true);
        self::assertSame($wrapped, (new CacheOptions())->reported($wrapped, "PARTITION BY HASH(DATE 'bad')", $session));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testPrefixResolvesAssignmentsAndLoadTargetsWithoutExecution(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $parser = $session->semantics()->parser();
        $assignment = 'SET unknown_prefix=(';
        $load = "LOAD DATA INFILE 'f' INTO TABLE t (";

        self::assertSame(1193, (new CacheOptions())->prefix($parser->tokenize($assignment), $assignment, null, $session)?->getCode());
        self::assertSame(1046, (new CacheOptions())->prefix($parser->tokenize($load), $load, null, $session)?->getCode());
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testReportedRefusesTwoModifiersBeforeASyntaxErrorIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $answers = $session->run('SELECT SQL_CACHE SQL_CACHE');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame("Option 'SQL_CACHE' used twice in statement", $answers[0]->getMessage());
        self::assertSame([['Warning', 1681, "'SQL_CACHE' is deprecated and will be removed in a future release."], ['Warning', 1681, "'SQL_CACHE' is deprecated and will be removed in a future release."], ['Error', 1225, "Option 'SQL_CACHE' used twice in statement"]], $session->diagnostics->conditions);
    }

    public function testReportedKeepsTheWarningsBeforeASyntaxErrorIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run('SELECT SQL_NO_CACHE 1 FROM DUAL WHERE (');

        self::assertSame([1681, 1064], array_column($session->diagnostics->conditions, 1));
    }

    public function testReportedKeepsBothConditionsOfAnUnknownAssignmentTargetIn56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $answers = $session->run('SET unknown_prefix=(');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1193, $answers[0]->getCode());
        self::assertSame([['Error', 1193, "Unknown system variable 'unknown_prefix'"], ['Error', 1193, "Unknown system variable 'unknown_prefix'"]], $session->diagnostics->conditions);
    }

    public function testReportedNamesTheClauseAUnionOperandMayNotWrite(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $answers = $session->run('SELECT 1 ORDER BY 1 UNION SELECT 2');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1221, 'Incorrect usage of UNION and ORDER BY'], [$answers[0]->getCode(), $answers[0]->getMessage()]);
    }

    public function testUsageReadsTheWrongUsageOfARefusedStatement(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $answers = $session->run('SELECT 1 INTO @a UNION SELECT 2');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame('Incorrect usage of UNION and INTO', $answers[0]->getMessage());
        self::assertNull((new CacheOptions())->usage(StatementError::ParseError->error('', 1)));
    }

    public function testPlacedPrefersARefusedPlacementOverAConflictIn56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $answers = $session->run('SELECT 1 FROM (SELECT SQL_CACHE SQL_CACHE 1) x');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1234, "Incorrect usage/placement of 'SQL_CACHE'"], [$answers[0]->getCode(), $answers[0]->getMessage()]);
        self::assertNull((new CacheOptions())->placed(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\CacheOptionConflict(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache), $session->analyze('SELECT 1')->statement, $session));
    }

    public function testPlacedLeavesAConflictInAnEarlierBlockFirstInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of SQL_CACHE and SQL_NO_CACHE');

        $session->query('(SELECT SQL_CACHE SQL_NO_CACHE 1) UNION (SELECT SQL_CACHE 2)');
    }

    public function testClashedReportsAConflictOfModifiersBeforeTheNestedJoinInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $session->run('SELECT SQL_CACHE SQL_NO_CACHE * FROM (a, a UNION SELECT 1)');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame(['1681', '1681', '1221'], array_map(static fn (array $row): string => (string) $row[1], $warnings->rows));
    }

    public function testLiteralRecordsTheWarningsBeforeARefusedLiteralInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $session->run("SELECT SQL_CACHE 1 FROM DUAL WHERE 1 = TIME 'x'");

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame(['1681', '1525'], array_map(static fn (array $row): string => (string) $row[1], $warnings->rows));
    }

    public function testReportedRefusesAllWithDistinctBeforeANestedJoinUnionInMySql57(): void
    {
        $answers = (new Instance('5.7.44'))->connect()->run('SELECT DISTINCT ALL * FROM (t1 UNION SELECT 1)');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1221, 'Incorrect usage of ALL and DISTINCT'], [$answers[0]->getCode(), $answers[0]->getMessage()]);
    }

    public function testRefusalAnswersTheErrorOfAConflict(): void
    {
        $options = new CacheOptions();

        self::assertSame([1234, 1225, 1221], [$options->refusal(['SQL_CACHE', ''])->getCode(), $options->refusal(['SQL_CACHE', 'SQL_CACHE'])->getCode(), $options->refusal(['ALL', 'DISTINCT'])->getCode()]);
    }

    public function testPlacedRefusesAllWithDistinctInAnEarlierBlockInMySql56(): void
    {
        $answers = (new Instance('5.6.51'))->connect()->run('SELECT ALL DISTINCT 1 UNION SELECT SQL_CACHE SQL_NO_CACHE 2');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1221, 'Incorrect usage of ALL and DISTINCT'], [$answers[0]->getCode(), $answers[0]->getMessage()]);
    }

    public function testUndeclaredKeepsTheWarningsOfTheWholeStatementInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $answers = $session->run('SELECT SQL_CACHE x INTO nov FOR UPDATE UNION SELECT SQL_NO_CACHE 1');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([1327, ['1681', '1681', '1327']], [$answers[0]->getCode(), array_column($warnings->rows, 1)]);
    }
    public function testBoundsStopsAtTheSyntaxErrorInMySql56AndChecksUpToItInMySql57(): void
    {
        $tokens = (new Instance('5.6.51'))->connect()->semantics()->parser()->tokenize('SELECT 1 FROM t WHERE (');
        $cause = new SyntaxException($tokens[5], [], '');

        self::assertSame([[22, 22, null, null], [23, 22, null, null]], [(new CacheOptions())->bounds($tokens, $cause, null, true, 23, GrammarRelease::MySql5651), (new CacheOptions())->bounds($tokens, $cause, null, true, 23, GrammarRelease::MySql5744)]);
    }

    public function testCauseAnswersTheSyntaxErrorBehindAnError(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT');
        $cause = new SyntaxException($tokens[0], [], '');
        $error = new SqlError(StatementError::ParseError, 'refused', new RuntimeException('read', 0, $cause));

        self::assertSame([$cause, null], [(new CacheOptions())->cause($error), (new CacheOptions())->cause(StatementError::ParseError->error('', 1))]);
    }

    public function testRecordedRecordsTheWarningsInTheOrderOfTheirTokensAndTheRefusal(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $error = (new CacheOptions())->recorded([17 => Deprecated::NoCache, 7 => Deprecated::Cache], ['SQL_CACHE', 'SQL_NO_CACHE'], StatementError::ParseError->error('', 1), $session);

        self::assertSame([1221, true], [$error->getCode(), $error->recorded]);
        self::assertSame([Deprecated::Cache->value, Deprecated::NoCache->value, 'Incorrect usage of SQL_CACHE and SQL_NO_CACHE'], array_column($session->diagnostics->conditions, 2));
    }

    public function testVariableAnswersAnUndeclaredVariableOnlyOnceMySql57HasParsedTheStatement(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $tokens = $session->semantics()->parser()->tokenize('SELECT 1 INTO v');

        self::assertSame(['v', null, null], [(new CacheOptions())->variable($tokens, 100, true, true, $session), (new CacheOptions())->variable($tokens, 100, false, true, $session), (new CacheOptions())->variable($tokens, 100, true, false, $session)]);
    }

    public function testConditionReportsARejectedOperandBeforeLaterSyntax(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $error = new SqlError(StatementError::ParseError, 'later syntax');

        $condition = (new CacheOptions())->condition($error, "SIGNAL SQLSTATE '00000' SET MESSAGE_TEXT = +", $session);

        self::assertSame(1407, $condition?->getCode());
        self::assertTrue($condition->recorded);
        self::assertNull((new CacheOptions())->condition($error, "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = +", $session));
    }
}
