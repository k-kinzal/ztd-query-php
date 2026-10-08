<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Problems::class)]
#[Small]
final class ProblemsTest extends TestCase
{
    public function testRaiseLeavesAStatementWithoutDiagnostics(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t');
        (new Problems())->raise($operation, $session);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRaiseRaisesAnUndeclaredVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: x');

        $session->query('SELECT 1 INTO x');
    }

    public function testRaiseRaisesTheNameTheServerResolvesFirst(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t WHERE y = 1 ORDER BY z');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'y' in 'where clause'");

        (new Problems())->raise($operation, $session);
    }

    public function testRaiseRaisesTheSelectListBeforeWhere(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'x' in 'field list'");

        $session->query('SELECT x FROM t WHERE y = 1');
    }

    public function testRaiseRaisesADerivedTableFirst(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'q' in 'field list'");

        $session->query('SELECT x FROM (SELECT q FROM t) AS s');
    }

    public function testRaiseIgnoresNonGroupedColumnsWithoutOnlyFullGroupBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE TABLE t (a INT, b INT); SET sql_mode = ''");
        $result = $session->query('SELECT b, COUNT(*) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '0']], $result->rows);
    }

    public function testErrorNamesTheClauseItIsGiven(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $diagnostic = $session->analyze('SELECT x FROM t')->facts->diagnostics[0];
        $error = (new Problems())->error($diagnostic, $session, 'having clause');

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

    public function testMisuseReportsAStarWithoutTables(): void
    {
        $error = (new Problems())->misuse(new Misuse(MisuseRule::StarWithoutTables));

        self::assertSame(1096, $error->getCode());
        self::assertSame('HY000', $error->sqlState());
        self::assertSame('No tables used', $error->getMessage());
    }

    public function testMisuseReportsADerivedTableWithoutAlias(): void
    {
        $error = (new Problems())->misuse(new Misuse(MisuseRule::DerivedWithoutAlias));

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

    public function testRaiseRaisesTheErrorsOfTheParserFirst(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        $session->run('SELECT 1 FROM nope FOR UPDATE OF p.zz');
        self::assertSame('Unresolved table name `p`.`zz` in locking clause.', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT nosuch(1 AS x) FROM nope');
        self::assertSame('Incorrect parameters in the call to stored function `nosuch`', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT zz, abs(1 AS x) FROM w');
        self::assertSame(1583, $session->diagnostics->conditions[0][1]);
        $session->run('SELECT * FROM w WINDOW x AS (), x AS () ORDER BY zz');
        self::assertSame("Unknown column 'zz' in 'order clause'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT ROW_NUMBER() OVER zz, yy FROM w');
        self::assertSame("Window name 'zz' is not defined.", $session->diagnostics->conditions[0][2]);
    }

    public function testRaiseRaisesAFunctionTheServerDoesNotFindWhereItResolvesTheCall(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');
        $without = (new Instance())->connect();

        $error = $session->run('SELECT nosuch(1)')[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1305, '42000', 'FUNCTION p.nosuch does not exist'], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
        $session->run('SELECT x.NoSuch(1)');
        self::assertSame('FUNCTION x.NoSuch does not exist', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT nosuch(1), zz FROM w');
        self::assertSame('FUNCTION p.nosuch does not exist', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT zz FROM w WHERE nosuch(1)');
        self::assertSame("Unknown column 'zz' in 'field list'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT nosuch(1) FROM nope');
        self::assertSame("Table 'p.nope' doesn't exist", $session->diagnostics->conditions[0][2]);
        $without->run('SELECT nosuch(1)');
        self::assertSame(1046, $without->diagnostics->conditions[0][1]);
    }

    public function testRaiseLeavesTheMissingTablesOfDropTableToTheCommand(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $without = (new Instance())->connect();

        $session->run('DROP TABLE nope');
        self::assertSame([['Error', 1051, "Unknown table 'p.nope'"]], $session->diagnostics->conditions);
        $without->run('DROP TABLE nope');
        self::assertSame(1046, $without->diagnostics->conditions[0][1]);
    }

    public function testRaiseNamesTheClauseOfAPositionOutsideTheSelectList(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        $session->run('SELECT a FROM w ORDER BY 3');
        self::assertSame([['Error', 1054, "Unknown column '3' in 'order clause'"]], $session->diagnostics->conditions);
        $session->run('SELECT a FROM w GROUP BY 3');
        self::assertSame("Unknown column '3' in 'group statement'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT a FROM w UNION SELECT a FROM w ORDER BY 3');
        self::assertSame("Unknown column '3' in 'order clause'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT a FROM w ORDER BY 3, zz');
        self::assertSame("Unknown column '3' in 'order clause'", $session->diagnostics->conditions[0][2]);
    }

    public function testParsedHoldsForTheProblemsOfTheParser(): void
    {
        self::assertTrue(Problems::parsed(new WrongArgumentCount(new Name('abs'), 0)));
        self::assertFalse(Problems::parsed(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t')))));
        self::assertFalse(Problems::parsed(new Misuse(MisuseRule::DuplicateWindow, new Name('w'))));
    }

    public function testLateHoldsForAWindowDefinedTwice(): void
    {
        self::assertTrue(Problems::late(new Misuse(MisuseRule::DuplicateWindow, new Name('w'))));
        self::assertFalse(Problems::late(new Misuse(MisuseRule::UnknownWindow, new Name('w'))));
    }

    public function testUndeclaredHoldsForACallOfAFunctionThatIsNotNative(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $operation = $session->analyze('SELECT nosuch(1), abs(1)');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->items[0]->expression);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[1]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->items[1]->expression);

        self::assertTrue(Problems::undeclared($operation->statement->items[0]->expression, $operation));
        self::assertFalse(Problems::undeclared($operation->statement->items[1]->expression, $operation));
    }

    public function testProblemAnswersTheProblemOfAnOrdinalAndOfACall(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $operation = $session->analyze('SELECT nosuch(1) ORDER BY 2');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->items[0]->expression);
        self::assertInstanceOf(OutputOrdinal::class, $operation->statement->orderBy[0]->expression);

        self::assertSame($operation->statement->items[0]->expression, (new Problems())->problem($operation->statement->items[0]->expression, $operation));
        self::assertInstanceOf(OrdinalOutOfRange::class, (new Problems())->problem($operation->statement->orderBy[0]->expression, $operation));
    }

    public function testRoutineNamesTheDatabaseOfTheCall(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');

        self::assertSame('FUNCTION p.f does not exist', (new Problems())->routine(new FunctionCall(new Name('f')), $session)->getMessage());
        self::assertSame('FUNCTION q.f does not exist', (new Problems())->routine(new FunctionCall(new Name('f'), [], new Name('q')), $session)->getMessage());
        self::assertSame(1046, (new Problems())->routine(new FunctionCall(new Name('f')), (new Instance())->connect())->getCode());
    }

    public function testWriteNamesTheStatementOfATargetThatIsNotUpdatable(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $update = $session->analyze('UPDATE (SELECT 1 a) d SET a = 1')->statement;
        self::assertInstanceOf(Update::class, $update);

        self::assertSame('The target table d of the UPDATE is not updatable', (new Problems())->write(new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('d')), $update)->getMessage());
        self::assertSame('The target table d of the DELETE is not updatable', (new Problems())->write(new WriteMisuse(WriteRule::NonUpdatableTarget, new Name('d')), null)->getMessage());
        self::assertSame([1054, "Unknown column '*' in 'field list'"], [(new Problems())->write(new WriteMisuse(WriteRule::WildcardColumn), null)->getCode(), (new Problems())->write(new WriteMisuse(WriteRule::WildcardColumn), null)->getMessage()]);
    }

    public function testClosingHoldsForTheProblemsAtTheEndOfAQueryBlock(): void
    {
        self::assertTrue(Problems::closing(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t')))));
        self::assertTrue(Problems::closing(new Misuse(MisuseRule::RepeatedLockedTable, new Name('t'))));
        self::assertTrue(Problems::closing(new UndeclaredVariable(new Name('n'))));
        self::assertFalse(Problems::closing(new WrongArgumentCount(new Name('abs'), 0)));
    }

    public function testRaiseIgnoresAnUnknownColumnOfACommonTableNoQueryReads(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        self::assertInstanceOf(ResultSet::class, $session->query('WITH x AS (SELECT nosuch FROM w) SELECT a FROM w')[0]);
        $session->run('WITH x AS (SELECT nosuch FROM w) SELECT * FROM x');
        self::assertSame("Unknown column 'nosuch' in 'field list'", $session->diagnostics->conditions[0][2]);
    }

    public function testRaiseRaisesAJsonTablePathAfterOpeningTablesAndBeforeNames(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        $session->run("UPDATE JSON_TABLE('[]', 'text' COLUMNS (q FOR ORDINALITY)) AS j SET nosuch = 1");
        self::assertSame([['Error', 3143, 'Invalid JSON path expression. The error is around character position 1.']], $session->diagnostics->conditions);
        $session->run("SELECT * FROM JSON_TABLE('[]', 'text' COLUMNS (q FOR ORDINALITY)) AS j, nosuch");
        self::assertSame(1146, $session->diagnostics->conditions[0][1]);
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

    public function testReadRaisesAnUnknownCollationAndAnUnknownTableOfAMultipleTableDeleteBeforeOpeningTables(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT, b INT)');

        $session->run("SELECT nosuch FROM nosuch WHERE 'a' COLLATE zz");
        self::assertSame([['Error', 1273, "Unknown collation: 'zz'"]], $session->diagnostics->conditions);
        $session->run('DELETE x.* FROM w PARTITION (p0), nosuch');
        self::assertSame([['Error', 1109, "Unknown table 'x' in MULTI DELETE"]], $session->diagnostics->conditions);
    }

    public function testErrorReportsTheRowOfAValuesStatementAndAnEmptyRow(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');

        $session->run('VALUES ROW(1), ROW(2, 3)');
        self::assertSame([['Error', 1136, "Column count doesn't match value count at row 2"]], $session->diagnostics->conditions);
        $session->run('SELECT * FROM (VALUES ROW()) AS x');
        self::assertSame([['Error', 3942, 'Each row of a VALUES clause must have at least one column, unless when used as source in an INSERT statement.']], $session->diagnostics->conditions);
    }

    public function testReadRaisesATableLockedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3569);
        $this->expectExceptionMessage('Table t appears in multiple locking clauses.');

        $session->query('TABLE t LOCK IN SHARE MODE LOCK IN SHARE MODE');
    }

    public function testReadRaisesALockingClauseBeforeAnIntoVariable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3568);
        $this->expectExceptionMessage('Unresolved table name `u` in locking clause.');

        $session->query('SELECT * FROM t FOR SHARE OF u INTO v');
    }

    public function testReadRaisesATableAliasUsedTwiceBeforeTheLockingClauses(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 't'");

        $session->query('SELECT * FROM t, t FOR SHARE OF u');
    }

    public function testReadRaisesAnUndeclaredVariableOfLimitOnlyWhereTheCommonTableIsUsed(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('WITH c AS (SELECT 1 LIMIT n) SELECT 1')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['1']], $reply->rows);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);

        $session->query('WITH c AS (SELECT 1 LIMIT n) SELECT * FROM c');
    }

    public function testRepeatedFindsACommonTableDefinedTwiceInsideAnUnusedOne(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 'c'");

        $session->query('WITH d AS (WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1) SELECT 1');
    }

    public function testAfterReadingHoldsForTheDeprecationOfIntoInsideAQuery(): void
    {
        self::assertTrue(Problems::afterReading(new Deprecation(Deprecated::IntoInsideQuery)));
        self::assertFalse(Problems::afterReading(new Deprecation(Deprecated::BinaryOperator)));
    }

    public function testPreparedRefusesQualifyBeforeResolvingAnyName(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6037);
        $this->expectExceptionMessage("'QUALIFY clause' can be used only if the hypergraph optimizer is enabled.");

        $session->query('SELECT x.* QUALIFY 1');
    }

    public function testPreparedRefusesCubeInAStatementWithoutTables(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6033);
        $this->expectExceptionMessage("'CUBE' is not supported");

        $session->query('SELECT * FROM DUAL GROUP BY CUBE (1)');
    }

    public function testReachedLeavesOutACommonTableNoReferenceNames(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('WITH c AS (SELECT 1 QUALIFY 1), e AS (SELECT 2) SELECT * FROM e')->statement;
        $reached = (new Problems())->reached($statement);
        $result = $session->query('WITH c AS (SELECT 1 QUALIFY 1) SELECT 1')[0];

        self::assertCount(1, array_filter($reached, static fn (object $node): bool => $node instanceof CommonTableExpression));
        self::assertInstanceOf(ResultSet::class, $result);
    }

    public function testMisuseNamesATableLockedTwice(): void
    {
        self::assertSame('Table t appears in multiple locking clauses.', (new Problems())->misuse(new Misuse(MisuseRule::RepeatedLockedTable, new Name('t')))->getMessage());
        self::assertSame('Table `d`.`t` appears in multiple locking clauses.', (new Problems())->misuse(new Misuse(MisuseRule::RepeatedLockedTable, new QualifiedName(new Name('t'), new Name('d'))))->getMessage());
    }

    public function testMisuseQuotesTheLockedTable(): void
    {
        self::assertSame('Unresolved table name `W` in locking clause.', (new Problems())->misuse(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('W'))))->getMessage());
        self::assertSame(3568, (new Problems())->misuse(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('W'))))->getCode());
    }

    public function testPrecisionNamesTheClockWithItsPrecisionModulo256(): void
    {
        $error = (new Problems())->precision(new ClockCall(Clock::CurrentTime, new Numeral('00000000058387')));

        self::assertSame([1426, "Too-big precision 19 specified for 'curtime'. Maximum is 6."], [$error->getCode(), $error->getMessage()]);
    }

    public function testRaiseRaisesATooBigClockPrecisionWhereTheServerResolvesIt(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $result = $session->query('SELECT CURTIME(256), UTC_TIMESTAMP(258) FROM DUAL WHERE 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[8, 0], [22, 2]], [[$result->columns[0]->length, $result->columns[0]->decimals], [$result->columns[1]->length, $result->columns[1]->decimals]]);
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Too-big precision 7 specified for 'utc_time'. Maximum is 6.");
        $session->query('SELECT UTC_TIME(263), nosuch FROM t');
    }

    public function testReadRaisesATooBigCastPrecisionBeforeOpeningAnyTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1426);
        $this->expectExceptionMessage("Too-big precision 263 specified for 'CAST'. Maximum is 6.");
        $session->query('SELECT CAST(NOW() AS TIME(263)) FROM nosuch');
    }
}
