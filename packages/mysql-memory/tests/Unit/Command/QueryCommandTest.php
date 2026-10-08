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

    public function testIntoAssignsTheFirstRowBeforeRefusingMoreRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');
        $session->run('SELECT a FROM t ORDER BY a INTO @x');
        $value = $session->query('SELECT @x')[0];

        self::assertInstanceOf(ResultSet::class, $value);
        self::assertSame([['1']], $value->rows);
    }

    public function testDestinationFindsTheIntoOfTheLastOperandOfASetOperation(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT 1 UNION (SELECT 2 INTO @z)')->statement;

        self::assertInstanceOf(Query::class, $statement);
        self::assertInstanceOf(IntoVariables::class, (new QueryCommand())->destination($statement));
    }

    public function testFileRefusesAnEnclosingStringOfMoreThanOneCharacter(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1083);
        $this->expectExceptionMessage('Field separator argument is not what is expected; check the manual');

        $session->query("SELECT 1 INTO OUTFILE '/tmp/out' FIELDS ENCLOSED BY 0b01 ESCAPED BY 'ab'");
    }

    public function testFileWarnsAboutASeparatorOutsideAsciiBeforeRefusingTheFile(): void
    {
        $session = (new Instance())->connect();
        $session->run("SELECT 1 INTO OUTFILE '/tmp/out' FIELDS TERMINATED BY 0xC3A9 ENCLOSED BY 'é'");

        self::assertSame([['Warning', 1638, 'Non-ASCII separator arguments are not fully supported'], ['Error', 1290, 'The MySQL server is running with the --secure-file-priv option so it cannot execute this statement']], $session->diagnostics->conditions);
    }

    public function testFileRefusesTheFileBeforeTheQueryRuns(): void
    {
        $session = (new Instance())->connect();
        $session->run("SELECT CAST('x' AS SIGNED) INTO DUMPFILE '/tmp/out'");

        self::assertSame([['Error', 1290, 'The MySQL server is running with the --secure-file-priv option so it cannot execute this statement']], $session->diagnostics->conditions);
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

    public function testCalculatesReadsTheOptionOfTheFirstSelect(): void
    {
        $session = (new Instance())->connect();
        $first = $session->analyze('SELECT SQL_CALC_FOUND_ROWS 1 UNION SELECT 2')->statement;
        $plain = $session->analyze('(SELECT 1 LIMIT 1)')->statement;

        self::assertInstanceOf(Query::class, $first);
        self::assertInstanceOf(Query::class, $plain);
        self::assertSame([true, false], [(new QueryCommand())->calculates($first), (new QueryCommand())->calculates($plain)]);
    }

    public function testExecuteSendsTheColumnsOfAUnionAsNotNullWhenItsFirstColumnIsInMySql91(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, a INT)');
        $first = $session->query('SELECT id, a FROM t UNION SELECT id, a FROM t')[0];
        $second = $session->query('SELECT a, id FROM t UNION SELECT a, id FROM t')[0];
        $intersected = $session->query('SELECT id, a FROM t INTERSECT SELECT id, a FROM t')[0];
        $older = (new Instance('8.4.7'))->connect();
        $older->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, a INT)');
        $kept = $older->query('SELECT id, a FROM t UNION SELECT id, a FROM t')[0];
        $notNull = static fn (ResultSet $result): array => array_map(static fn (ResultColumn $column): bool => ($column->flags & ColumnFlag::NotNull->value) !== 0, $result->columns);

        self::assertInstanceOf(ResultSet::class, $first);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertInstanceOf(ResultSet::class, $intersected);
        self::assertInstanceOf(ResultSet::class, $kept);
        self::assertSame([[true, true], [false, false], [true, false], [true, false]], [$notNull($first), $notNull($second), $notNull($intersected), $notNull($kept)]);
    }

    public function testUnitesTellsWhetherTheOutermostOperationIsAUnion(): void
    {
        $session = (new Instance())->connect();
        $command = new QueryCommand();
        $ordered = $session->analyze('(SELECT 1 UNION SELECT 2) ORDER BY 1')->statement;
        $nested = $session->analyze('SELECT 1 UNION SELECT 2 INTERSECT SELECT 2')->statement;
        $intersected = $session->analyze('(SELECT 1 UNION SELECT 2) INTERSECT SELECT 2')->statement;
        $single = $session->analyze('SELECT 1')->statement;

        self::assertInstanceOf(Query::class, $ordered);
        self::assertInstanceOf(Query::class, $nested);
        self::assertInstanceOf(Query::class, $intersected);
        self::assertInstanceOf(Query::class, $single);
        self::assertSame([true, true, false, false], [$command->unites($ordered), $command->unites($nested), $command->unites($intersected), $command->unites($single)]);
    }
}
