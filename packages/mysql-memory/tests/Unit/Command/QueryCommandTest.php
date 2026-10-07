<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\QueryCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Query;

#[CoversClass(QueryCommand::class)]
#[Small]
final class QueryCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new QueryCommand())->clearsDiagnostics());
    }

    public function testExecuteAnswersTheRowsOfTheQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(5)); INSERT INTO t VALUES (2, 'y'), (1, 'x')");

        $result = $session->query('SELECT b, a * 10 FROM t ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['x', '10'], ['y', '20']], $result->rows);
    }

    public function testExecuteAssignsTheRowToTheVariablesOfInto(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(5)); INSERT INTO t VALUES (1, 'x'), (2, 'y')");

        $reply = $session->query('SELECT a, b INTO @p, @q FROM t WHERE a = 2')[0];
        $result = $session->query('SELECT @p, @q')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 0], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', 'y']], $result->rows);
    }

    public function testDestinationFindsTheIntoOfAParenthesizedQuery(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('(SELECT 3) INTO @z')->statement;

        self::assertInstanceOf(Query::class, $statement);
        self::assertInstanceOf(IntoVariables::class, (new QueryCommand())->destination($statement));
    }

    public function testDestinationAnswersNullForAQueryWithoutInto(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT 1 UNION SELECT 2')->statement;

        self::assertInstanceOf(Query::class, $statement);
        self::assertNull((new QueryCommand())->destination($statement));
    }

    public function testIntoAssignsNothingWithAWarningWhenNoRowIsFound(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x = 5');

        $reply = $session->query('SELECT 1 INTO @x FROM DUAL WHERE FALSE')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $value = $session->query('SELECT @x')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 1], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1329', 'No data - zero rows fetched, selected, or processed']], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $value);
        self::assertSame([['5']], $value->rows);
    }

    public function testIntoRefusesMoreThanOneRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1172);
        $this->expectExceptionMessage('Result consisted of more than one row');

        $session->query('SELECT a INTO @x FROM t');
    }

    public function testIntoRefusesADifferentNumberOfVariables(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1222);
        $this->expectExceptionMessage('The used SELECT statements have a different number of columns');

        $session->query('SELECT 1, 2 INTO @x');
    }

    public function testIntoRefusesAFileUnderSecureFilePriv(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1290);
        $this->expectExceptionMessage('The MySQL server is running with the --secure-file-priv option so it cannot execute this statement');

        $session->query("SELECT 1 INTO OUTFILE '/tmp/out'");
    }

    public function testDomainHoldsAnIntegerAsABigint(): void
    {
        $domain = (new QueryCommand())->domain(new ResultColumn('a', Field::Long, 11, 0, ColumnFlag::Unsigned->value, 63));

        self::assertSame([Kind::Integer, Field::LongLong, 21, true], [$domain->kind, $domain->field, $domain->length, $domain->unsigned]);
    }

    public function testDomainHoldsADecimalWithItsScale(): void
    {
        $domain = (new QueryCommand())->domain(new ResultColumn('a', Field::NewDecimal, 6, 2, 0, 63));

        self::assertSame([Kind::Decimal, Field::NewDecimal, 67, 2], [$domain->kind, $domain->field, $domain->length, $domain->decimals]);
    }

    public function testDomainHoldsAFloatAsADouble(): void
    {
        $domain = (new QueryCommand())->domain(new ResultColumn('a', Field::Float, 12, 31, 0, 63));

        self::assertSame([Kind::Double, Field::Double], [$domain->kind, $domain->field]);
    }

    public function testDomainHoldsAnyOtherValueAsABinaryString(): void
    {
        $domain = (new QueryCommand())->domain(new ResultColumn('a', Field::VarString, 20, 0, 0, 255));

        self::assertSame([Kind::String, Field::MediumBlob, 16777216, 'binary'], [$domain->kind, $domain->field, $domain->length, $domain->collation->name]);
    }
}
