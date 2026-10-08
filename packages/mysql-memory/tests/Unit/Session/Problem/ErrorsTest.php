<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Errors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CacheOptionConflict;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(Errors::class)]
#[Small]
final class ErrorsTest extends TestCase
{
    public function testErrorReportsABucketCountOutOfRange(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("Number of buckets value is out of range in 'ANALYZE TABLE'");

        $session->query('ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 0 BUCKETS');
    }

    public function testErrorNamesTheClauseItIsGiven(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $diagnostic = $session->analyze('SELECT x FROM t')->facts->diagnostics[0];
        $error = (new Errors())->error($diagnostic, $session, 'having clause');

        self::assertSame(1054, $error->getCode());
        self::assertSame("Unknown column 'x' in 'having clause'", $error->getMessage());
    }

    public function testErrorReportsAMissingTableWithoutADatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query('SELECT * FROM nowhere');
    }

    public function testErrorReportsAMissingTableOfAnotherDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE shop');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'shop.items' doesn't exist");

        $session->query('SELECT * FROM shop.items');
    }

    public function testErrorReportsAMissingTableOfTheCurrentDatabase(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.items' doesn't exist");

        $session->query('SELECT * FROM items');
    }

    public function testErrorReportsAMissingQualifiedColumn(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 't2.a' in 'field list'");

        $session->query('SELECT t2.a FROM t');
    }

    public function testErrorReportsAnAmbiguousColumn(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1052);
        $this->expectExceptionMessage("Column 'a' in where clause is ambiguous");

        $session->query('SELECT 1 FROM t AS x, t AS y WHERE a = 1');
    }

    public function testErrorReportsANonUniqueTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 't'");

        $session->query('SELECT * FROM t, t');
    }

    public function testErrorReportsAValueCountMismatchAtItsRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1136);
        $this->expectExceptionMessage("Column count doesn't match value count at row 2");

        $session->query('INSERT INTO t VALUES (1, 2), (3)');
    }

    public function testErrorReportsTheColumnsAnOperandShouldContain(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 2 column(s)');

        $session->query('SELECT ROW(1, 2) = 1');
    }

    public function testErrorReportsAWrongArgumentCount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1582);
        $this->expectExceptionMessage("Incorrect parameter count in the call to native function 'CONCAT'");

        $session->query('SELECT CONCAT()');
    }

    public function testErrorReportsMultiplePrimaryKeys(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1068);
        $this->expectExceptionMessage('Multiple primary key defined');

        $session->query('CREATE TABLE u (a INT PRIMARY KEY, b INT PRIMARY KEY)');
    }

    public function testErrorReportsATableThatExists(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1050);
        $this->expectExceptionMessage("Table 't' already exists");

        $session->query('CREATE TABLE t (b INT)');
    }

    public function testErrorReportsANonAggregatedColumnWithoutGroupBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1140);
        $this->expectExceptionMessage("In aggregated query without GROUP BY, expression #1 of SELECT list contains nonaggregated column 'd.t.b'; this is incompatible with sql_mode=only_full_group_by");

        $session->query('SELECT b, COUNT(*) FROM t');
    }

    public function testErrorReportsAColumnNotInGroupBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1055);
        $this->expectExceptionMessage("Expression #1 of SELECT list is not in GROUP BY clause and contains nonaggregated column 'd.t.b' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by");

        $session->query('SELECT b FROM t GROUP BY a');
    }

    public function testErrorReportsAnOrderColumnNotSelectedWithDistinct(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3065);
        $this->expectExceptionMessage("Expression #1 of ORDER BY clause is not in SELECT list, references column 'd.t.b' which is not in SELECT list; this is incompatible with DISTINCT");

        $session->query('SELECT DISTINCT a FROM t ORDER BY b');
    }

    public function testErrorReportsAnUnknownQualifierOfAStar(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1051);
        $this->expectExceptionMessage("Unknown table 't2'");

        $session->query('SELECT t2.* FROM t');
    }

    public function testErrorReportsAnUnknownTableOfAMultipleTableDelete(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1109);
        $this->expectExceptionMessage("Unknown table 't2' in MULTI DELETE");

        $session->query('DELETE t2 FROM t');
    }

    public function testErrorReportsSetOperandsOfDifferentWidths(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1222);
        $this->expectExceptionMessage('The used SELECT statements have a different number of columns');

        $session->query('SELECT 1 UNION SELECT 1, 2');
    }

    public function testErrorReportsIntoVariablesOfADifferentCount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1222);
        $this->expectExceptionMessage('The used SELECT statements have a different number of columns');

        $session->query('SELECT 1, 2 INTO @a');
    }

    public function testErrorReportsDerivedColumnNamesOfADifferentCount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1353);
        $this->expectExceptionMessage('In definition of view, derived table or common table expression, SELECT list and column names list have different column counts');

        $session->query('SELECT * FROM (SELECT 1, 2) AS x (a)');
    }

    public function testErrorReportsAnIllegalMixOfTwoCollations(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1267);
        $this->expectExceptionMessage("Illegal mix of collations (utf8mb4_bin,EXPLICIT) and (utf8mb4_general_ci,EXPLICIT) for operation '='");

        $session->query("SELECT ('a' COLLATE utf8mb4_bin) = ('b' COLLATE utf8mb4_general_ci)");
    }

    public function testErrorReportsAPartitionOfATableWithoutPartitions(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1747);
        $this->expectExceptionMessage('PARTITION () clause on non partitioned table');

        $session->query('SELECT * FROM t PARTITION (p0)');
    }

    public function testErrorReportsAnUnknownSystemVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1193);
        $this->expectExceptionMessage("Unknown system variable 'nosuch'");

        $session->query('SELECT @@nosuch');
    }

    public function testErrorReportsAVariableReadInTheWrongScope(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1238);
        $this->expectExceptionMessage("Variable 'warning_count' is a SESSION variable");

        $session->query('SELECT @@GLOBAL.warning_count');
    }

    public function testErrorReportsAnUnknownCollation(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1273);
        $this->expectExceptionMessage("Unknown collation: 'nosuch'");

        $session->query("SELECT 'a' COLLATE nosuch");
    }

    public function testErrorReportsAnUnknownCharacterSet(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1115);
        $this->expectExceptionMessage("Unknown character set: 'nosuch'");

        $session->query("SELECT CONVERT('a' USING nosuch)");
    }

    public function testErrorReportsACollationOfAnotherCharacterSet(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1253);
        $this->expectExceptionMessage("COLLATION 'latin1_swedish_ci' is not valid for CHARACTER SET 'utf8mb4'");

        $session->query("SELECT 'a' COLLATE latin1_swedish_ci");
    }

    public function testCountedReportsEachListOfADifferentLength(): void
    {
        $errors = array_map(static fn (CountMismatch $diagnostic): array => [(new Errors())->counted($diagnostic)->getCode(), (new Errors())->counted($diagnostic)->getMessage()], [
            new CountMismatch(CountedList::SetOperands, 1, 2),
            new CountMismatch(CountedList::IntoVariables, 1, 2),
            new CountMismatch(CountedList::ValueRows, 1, 2, 3),
            new CountMismatch(CountedList::DerivedColumns, 1, 2),
        ]);

        self::assertSame([
            [1222, 'The used SELECT statements have a different number of columns'],
            [1222, 'The used SELECT statements have a different number of columns'],
            [1136, "Column count doesn't match value count at row 3"],
            [1353, 'In definition of view, derived table or common table expression, SELECT list and column names list have different column counts'],
        ], $errors);
    }

    public function testMisuseReportsAStarWithoutTables(): void
    {
        $error = (new Errors())->misuse(new Misuse(MisuseRule::StarWithoutTables));

        self::assertSame(1096, $error->getCode());
        self::assertSame('HY000', $error->sqlState());
        self::assertSame('No tables used', $error->getMessage());
    }

    public function testMisuseReportsADerivedTableWithoutAlias(): void
    {
        $error = (new Errors())->misuse(new Misuse(MisuseRule::DerivedWithoutAlias));

        self::assertSame(1248, $error->getCode());
        self::assertSame('42000', $error->sqlState());
        self::assertSame('Every derived table must have its own alias', $error->getMessage());
    }

    public function testMisuseOfAStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1096);
        $this->expectExceptionMessage('No tables used');

        $session->query('SELECT *');
    }

    public function testErrorAnswersTheServerErrorOfEachDefinitionProblem(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');

        $error = $session->run('CREATE TABLE u2 (a INT, a INT)')[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1060, '42S21', "Duplicate column name 'a'"], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
        $session->run('CREATE TABLE t (a INT, KEY (zz))');
        self::assertSame("Key column 'zz' doesn't exist in table", $session->diagnostics->conditions[0][2]);
        $session->run('CREATE TABLE t (a INT, KEY (a, A))');
        self::assertSame("Duplicate column name 'A'", $session->diagnostics->conditions[0][2]);
        $session->run('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');
        self::assertSame(1171, $session->diagnostics->conditions[0][1]);
        $session->run('CREATE TABLE t (`a ` INT)');
        self::assertSame("Incorrect column name 'a '", $session->diagnostics->conditions[0][2]);
        $session->run('CREATE TABLE t (b INT AS (1)) SELECT 5 AS b');
        self::assertSame("The value specified for generated column 'b' in table 't' is not allowed.", $session->diagnostics->conditions[0][2]);
        $session->run('CREATE TABLE t (a INT) PARTITION BY KEY (zz) PARTITIONS 2');
        self::assertSame(1488, $session->diagnostics->conditions[0][1]);
        $session->run('DROP TABLE w, nope, w');
        self::assertSame("Not unique table/alias: 'w'", $session->diagnostics->conditions[0][2]);
    }

    public function testErrorAnswersTheServerErrorOfEachQueryProblem(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT, b INT)');

        $session->run('SELECT 1 FROM w WINDOW X AS (), x AS ()');
        self::assertSame([['Error', 3591, "Window 'X' is defined twice."]], $session->diagnostics->conditions);
        $session->run('SELECT ROW_NUMBER() OVER zz FROM w');
        self::assertSame("Window name 'zz' is not defined.", $session->diagnostics->conditions[0][2]);
        $session->run('WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1');
        self::assertSame("Not unique table/alias: 'c'", $session->diagnostics->conditions[0][2]);
        $session->run('WITH RECURSIVE c AS (SELECT * FROM c) SELECT * FROM c');
        self::assertSame("Recursive Common Table Expression 'c' should contain a UNION", $session->diagnostics->conditions[0][2]);
        $session->run('WITH RECURSIVE c (n) AS (SELECT n FROM c UNION SELECT 1) SELECT * FROM c');
        self::assertSame("Recursive Common Table Expression 'c' should have one or more non-recursive query blocks followed by one or more recursive ones", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT * FROM (SELECT 1 A, 2 a) d');
        self::assertSame("Duplicate column name 'a'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT * FROM (w JOIN w v ON 1) JOIN w u USING (A)');
        self::assertSame("Column 'a' in from clause is ambiguous", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT a AS x, b AS x FROM w ORDER BY x');
        self::assertSame("Column 'x' in order clause is ambiguous", $session->diagnostics->conditions[0][2]);
        $error = $session->run("SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS (x INT PATH '$'))")[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([3667, '42000', 'Every table function must have an alias'], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
        $session->run('SELECT GROUP_CONCAT(a) OVER () FROM w');
        self::assertSame("This version of MySQL doesn't yet support 'group_concat as window function'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT Abs(1 AS x)');
        self::assertSame("Incorrect parameters in the call to native function 'abs'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT get_dd_column_privileges(1, 2, 3)');
        self::assertSame("Access to native function 'get_dd_column_privileges' is rejected.", $session->diagnostics->conditions[0][2]);
    }

    public function testErrorAnswersTheServerErrorOfEachWriteProblem(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT, b INT); CREATE TABLE g (a INT, b INT AS (a + 1))');

        $session->run('INSERT INTO w (a, a) VALUES (1, 2)');
        self::assertSame("Column 'a' specified twice", $session->diagnostics->conditions[0][2]);
        $session->run('UPDATE g SET b = 1');
        self::assertSame(3105, $session->diagnostics->conditions[0][1]);
        $session->run('INSERT INTO w VALUES (1, 2) AS w ON DUPLICATE KEY UPDATE a = 1');
        self::assertSame("Not unique table/alias: 'w'", $session->diagnostics->conditions[0][2]);
        $session->run('INSERT INTO w VALUES (1, 2) AS n (x, x) ON DUPLICATE KEY UPDATE a = 1');
        self::assertSame("Duplicate column name 'x'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT * FROM (VALUES ROW(DEFAULT)) d');
        self::assertSame(3943, $session->diagnostics->conditions[0][1]);
        $session->run('UPDATE w, w v SET w.a = 1 LIMIT 1');
        self::assertSame('Incorrect usage of UPDATE and LIMIT', $session->diagnostics->conditions[0][2]);
        $session->run('WITH c AS (SELECT 1 a) UPDATE c AS z SET a = 1');
        self::assertSame('The target table z of the UPDATE is not updatable', $session->diagnostics->conditions[0][2]);
        $session->run('DELETE d FROM (SELECT 1 a) d');
        self::assertSame('The target table d of the DELETE is not updatable', $session->diagnostics->conditions[0][2]);
        $session->run('WITH c AS (SELECT 1 a) DELETE FROM c');
        self::assertSame('The target table c of the DELETE is not updatable', $session->diagnostics->conditions[0][2]);
    }

    public function testRoutineNamesTheDatabaseOfTheCall(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');

        self::assertSame('FUNCTION p.f does not exist', (new Errors())->routine(new FunctionCall(new Name('f')), $session)->getMessage());
        self::assertSame('FUNCTION q.f does not exist', (new Errors())->routine(new FunctionCall(new Name('f'), [], new Name('q')), $session)->getMessage());
        self::assertSame(1046, (new Errors())->routine(new FunctionCall(new Name('f')), (new Instance())->connect())->getCode());
    }

    public function testRoutineCountsTheArgumentsOfAStoredFunction(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE FUNCTION f(a INT) RETURNS INT DETERMINISTIC RETURN a');

        self::assertSame([1318, 'Incorrect number of arguments for FUNCTION p.f; expected 1, got 0'], [(new Errors())->routine(new FunctionCall(new Name('F')), $session)->getCode(), (new Errors())->routine(new FunctionCall(new Name('F')), $session)->getMessage()]);
    }

    public function testWriteNamesTheStatementOfATargetThatIsNotUpdatable(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $update = $session->analyze('UPDATE (SELECT 1 a) d SET a = 1')->statement;
        self::assertInstanceOf(Update::class, $update);

        self::assertSame('The target table d of the UPDATE is not updatable', (new Errors())->write(new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('d')), $update)->getMessage());
        self::assertSame('The target table d of the DELETE is not updatable', (new Errors())->write(new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('d')), null)->getMessage());
        self::assertSame([1054, "Unknown column '*' in 'field list'"], [(new Errors())->write(new WriteMisuse(WriteRule::WildcardColumn), null)->getCode(), (new Errors())->write(new WriteMisuse(WriteRule::WildcardColumn), null)->getMessage()]);
    }

    public function testJoiningHoldsForAColumnOfAUsingList(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT, b INT)');

        $session->run('SELECT nosuch FROM w JOIN w AS v USING (zz)');
        self::assertSame("Unknown column 'zz' in 'from clause'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT x.y.z FROM w');
        self::assertSame("Unknown column 'x.y.z' in 'field list'", $session->diagnostics->conditions[0][2]);
    }

    public function testErrorReportsTheRowOfAValuesStatementAndAnEmptyRow(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');

        $session->run('VALUES ROW(1), ROW(2, 3)');
        self::assertSame([['Error', 1136, "Column count doesn't match value count at row 2"]], $session->diagnostics->conditions);
        $session->run('SELECT * FROM (VALUES ROW()) AS x');
        self::assertSame([['Error', 3942, 'Each row of a VALUES clause must have at least one column, unless when used as source in an INSERT statement.']], $session->diagnostics->conditions);
    }

    public function testMisuseNamesATableLockedTwice(): void
    {
        self::assertSame('Table t appears in multiple locking clauses.', (new Errors())->misuse(new Misuse(MisuseRule::RepeatedLockedTable, new Name('t')))->getMessage());
        self::assertSame('Table `d`.`t` appears in multiple locking clauses.', (new Errors())->misuse(new Misuse(MisuseRule::RepeatedLockedTable, new QualifiedName(new Name('t'), new Name('d'))))->getMessage());
    }

    public function testMisuseQuotesTheLockedTable(): void
    {
        self::assertSame('Unresolved table name `W` in locking clause.', (new Errors())->misuse(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('W'))))->getMessage());
        self::assertSame(3568, (new Errors())->misuse(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('W'))))->getCode());
    }

    public function testPrecisionNamesTheClockWithItsPrecisionModulo256(): void
    {
        $error = (new Errors())->precision(new ClockCall(Clock::CurrentTime, new Numeral('00000000058387')));

        self::assertSame([1426, "Too-big precision 19 specified for 'curtime'. Maximum is 6."], [$error->getCode(), $error->getMessage()]);
    }

    public function testLocatedFollowsTheErrorOfAColumnOfMatchWithTheErrorOfAgainst(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $statement = $session->analyze('SELECT 1')->statement;
        $refusal = \MySqlMemory\Error\Family\DataError::NoDefaultForField->error('a');

        $matched = (new Errors())->located(new \SqlSemantics\Statement\Reference\Column\MissingColumn(new Name('x')), 'where clause', true, $session, $statement);

        self::assertSame($refusal, (new Errors())->located($refusal, 'field list', false, $session, $statement));
        self::assertSame("Too-big precision 7 specified for 'now'. Maximum is 6.", (new Errors())->located(new ClockCall(Clock::Now, new Numeral('7')), 'field list', false, $session, $statement)->getMessage());
        self::assertSame('FUNCTION d.f does not exist', (new Errors())->located(new FunctionCall(new Name('f')), 'field list', false, $session, $statement)->getMessage());
        self::assertSame([1054, "Unknown column 'x' in 'where clause'", [[1210, 'Incorrect arguments to AGAINST']]], [$matched->getCode(), $matched->getMessage(), $matched->following]);
    }

    public function testNamesAnswersTheErrorOfANameOrNull(): void
    {
        $errors = new Errors();

        self::assertSame(1046, $errors->names(new MissingTable(new QualifiedName(new Name('t'))), '', 'field list', null)?->getCode());
        self::assertSame("Table 'd.t' doesn't exist", $errors->names(new MissingTable(new QualifiedName(new Name('t'))), 'd', 'field list', null)?->getMessage());
        self::assertSame("Unknown column 's.t.x' in 'on clause'", $errors->names(new \SqlSemantics\Statement\Reference\Column\MissingColumn(new Name('x'), new QualifiedName(new Name('t'), new Name('s'))), 'd', 'on clause', null)?->getMessage());
        self::assertNull($errors->names(new Misuse(MisuseRule::StarWithoutTables), 'd', 'field list', null));
    }

    public function testQueryAnswersTheErrorOfARuleOfAQueryOrNull(): void
    {
        $errors = new Errors();

        self::assertSame("Unknown column '3' in 'group statement'", $errors->query(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange(3, 1), 'group statement')?->getMessage());
        self::assertSame("Unknown column '3' in 'order clause'", $errors->query(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange(3, 1), 'having clause')?->getMessage());
        self::assertSame('Undeclared variable: n', $errors->query(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable(new Name('n')), 'field list')?->getMessage());
        self::assertSame("Not unique table/alias: 'a'", $errors->query(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable(new Name('a')), 'field list')?->getMessage());
        self::assertNull($errors->query(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys(), 'field list'));
    }

    public function testCallAnswersTheErrorOfAWrongCallOfANativeFunctionOrNull(): void
    {
        $errors = new Errors();

        self::assertSame("Incorrect parameter count in the call to native function 'ABS'", $errors->call(new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount(new Name('ABS'), 0))?->getMessage());
        self::assertSame("Access to native function 'f' is rejected.", $errors->call(new \SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction(new Name('f')))?->getMessage());
        self::assertNull($errors->call(new Misuse(MisuseRule::StarWithoutTables)));
    }

    public function testDefinitionAnswersTheErrorOfAProblemOfADefinitionOrNull(): void
    {
        $errors = new Errors();

        self::assertSame([1068, 'Multiple primary key defined'], [$errors->definition(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys(), 'd')?->getCode(), $errors->definition(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys(), 'd')?->getMessage()]);
        self::assertNull($errors->definition(new Misuse(MisuseRule::StarWithoutTables), 'd'));
    }

    public function testManipulationAnswersTheErrorOfAProblemOfAWriteOrNull(): void
    {
        $errors = new Errors();

        self::assertSame("Column count doesn't match value count", $errors->manipulation(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch(2, 1), null)?->getMessage());
        self::assertSame("Column count doesn't match value count at row 3", $errors->manipulation(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch(2, 1, 3), null)?->getMessage());
        self::assertSame('Incorrect usage of UPDATE and LIMIT', $errors->manipulation(new WriteMisuse(WriteRule::LimitedMultipleUpdate), null)?->getMessage());
        self::assertNull($errors->manipulation(new Misuse(MisuseRule::StarWithoutTables), null));
    }

    public function testExpressionAnswersTheErrorOfAProblemOfAnExpressionOrNull(): void
    {
        $errors = new Errors();

        self::assertSame('Operand should contain 1 column(s)', $errors->expression(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2))?->getMessage());
        self::assertSame("Too-big precision 9 specified for 'CAST'. Maximum is 6.", $errors->expression(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision(9, 'CAST'))?->getMessage());
        self::assertSame("Unknown character set: 'zz'", $errors->expression(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset('zz'))?->getMessage());
        self::assertNull($errors->expression(new Misuse(MisuseRule::StarWithoutTables)));
    }

    public function testServerAnswersTheErrorOfAProblemOfAServerStatementOrNull(): void
    {
        $session = (new Instance())->connect();
        $errors = new Errors();

        self::assertSame("Unknown system variable 'nosuch'", $errors->server(new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable('nosuch'), $session, null)?->getMessage());
        self::assertSame("Number of buckets value is out of range in 'ANALYZE TABLE'", $errors->server(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange(new Numeral('0')), $session, null)?->getMessage());
        self::assertNull($errors->server(new Misuse(MisuseRule::StarWithoutTables), $session, null));
    }

    public function testErrorAnswersAnUnknownErrorForADiagnosticNoFamilyKnows(): void
    {
        $session = (new Instance())->connect();
        $known = (new Errors())->error(new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::DebugOnly), $session);
        $unknown = (new Errors())->error(new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::NamesExpression), $session);

        self::assertSame(1289, $known->getCode());
        self::assertSame([1105, 'SET NAMES takes a character set name, not an expression'], [$unknown->getCode(), $unknown->getMessage()]);
    }

    public function testNamedAnswersTheNameOfAMisuseWithoutItsDatabase(): void
    {
        self::assertSame(['w', 't', ''], [Errors::named(new Misuse(MisuseRule::UnknownWindow, new Name('w'))), Errors::named(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t'), new Name('d')))), Errors::named(new Misuse(MisuseRule::StarWithoutTables))]);
    }

    public function testQuotedQuotesTheTableAfterItsDatabase(): void
    {
        self::assertSame(['`d`.`t`', '`t`', '`t`'], [Errors::quoted(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t'), new Name('d')))), Errors::quoted(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t')))), Errors::quoted(new Misuse(MisuseRule::RepeatedLockedTable, new Name('t')))]);
    }

    public function testUnopenedReportsADatabaseThatDoesNotExistAndATableInformationSchemaLacks(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $errors = new Errors();

        $database = $errors->unopened(new MissingTable(new QualifiedName(new Name('t'), new Name('nodb'))), $session);
        $information = $errors->unopened(new MissingTable(new QualifiedName(new Name('NoSuch'), new Name('INFORMATION_SCHEMA'))), $session);

        self::assertInstanceOf(SqlError::class, $database);
        self::assertInstanceOf(SqlError::class, $information);
        self::assertSame([[1049, "Unknown database 'nodb'"], [1109, "Unknown table 'NOSUCH' in information_schema"]], [[$database->getCode(), $database->getMessage()], [$information->getCode(), $information->getMessage()]]);
        self::assertNull($errors->unopened(new MissingTable(new QualifiedName(new Name('t'), new Name('d'))), $session));
    }

    public function testUnknownNamesTheTableIn56And57AndTheDatabaseLater(): void
    {
        self::assertSame([1146, "Table 'nodb.t' doesn't exist"], [Errors::unknown('nodb', 't', GrammarRelease::MySql5744)->getCode(), Errors::unknown('nodb', 't', GrammarRelease::MySql5744)->getMessage()]);
        self::assertSame([1049, "Unknown database 'nodb'"], [Errors::unknown('nodb', 't', GrammarRelease::MySql847)->getCode(), Errors::unknown('nodb', 't', GrammarRelease::MySql847)->getMessage()]);
    }

    public function testQueryAnswersTheErrorsOfConflictingQueryCacheModifiers(): void
    {
        $errors = new Errors();
        $twice = $errors->query(new CacheOptionConflict(SelectOption::Cache, SelectOption::Cache), 'field list');
        $both = $errors->query(new CacheOptionConflict(SelectOption::NoCache, SelectOption::Cache), 'field list');

        self::assertSame([1225, 1221, 'Incorrect usage of SQL_NO_CACHE and SQL_CACHE'], [$twice?->getCode(), $both?->getCode(), $both?->getMessage()]);
    }
}
