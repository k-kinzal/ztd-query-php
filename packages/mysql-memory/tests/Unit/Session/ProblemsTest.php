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
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;

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
        $error = (new Problems())->misuse(MisuseRule::StarWithoutTables);

        self::assertSame(1096, $error->getCode());
        self::assertSame('HY000', $error->sqlState());
        self::assertSame('No tables used', $error->getMessage());
    }

    public function testMisuseReportsADerivedTableWithoutAlias(): void
    {
        $error = (new Problems())->misuse(MisuseRule::DerivedWithoutAlias);

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
}
