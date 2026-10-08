<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\TableQueries;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(TableQueries::class)]
#[Small]
final class TableQueriesTest extends TestCase
{
    public function testCreateCreatesTheTableWithTheRowsOfTheQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(10) DEFAULT 'x'); INSERT INTO t VALUES (1,'p'),(2,NULL)");

        $reply = $session->query('CREATE TABLE e (z INT, a INT DEFAULT 7) SELECT a, b, a + 1 AS x FROM t')[0];
        $rows = $session->query('SELECT * FROM e')[0];
        $defaulted = $session->query('INSERT INTO e (z) VALUES (9)')[0];
        $after = $session->query('SELECT * FROM e WHERE z = 9')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 'Records: 2  Duplicates: 0  Warnings: 0'], [$reply->affectedRows, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([[null, '1', 'p', '2'], [null, '2', null, '3']], $rows->rows);
        self::assertSame(['LONG', 11, 'LONGLONG', 12], [$rows->columns[1]->type->name === 'Long' ? 'LONG' : '', $rows->columns[1]->length, $rows->columns[3]->type->name === 'LongLong' ? 'LONGLONG' : '', $rows->columns[3]->length]);
        self::assertInstanceOf(Completion::class, $defaulted);
        self::assertInstanceOf(ResultSet::class, $after);
        self::assertSame([['9', '7', 'x', '0']], $after->rows);
    }

    public function testCreateSkipsARepeatedRowWithIgnore(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $reply = $session->query("CREATE TABLE f (UNIQUE (b)) IGNORE SELECT 'a' AS b UNION ALL SELECT 'a'")[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 'Records: 2  Duplicates: 1  Warnings: 1'], [$reply->affectedRows, $reply->info]);
    }

    public function testCreateRefusesARepeatedRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $error = $session->run("CREATE TABLE f (UNIQUE (b)) SELECT 'a' AS b UNION ALL SELECT 'a'")[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1062, "Duplicate entry 'a' for key 'f.b'"], [$error->getCode(), $error->getMessage()]);
        self::assertNull($session->instance->dictionary->table('d', 'f'));
    }

    public function testCreateRefusesAColumnNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1060);

        $session->query('CREATE TABLE g SELECT 1 AS a, 2 AS a');
    }

    public function testElementsLeavesOutTheDeclaredColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $plan = new \MySqlMemory\Plan\QueryPlan(new \MySqlMemory\Plan\Path\Source\SingleRow(), [new Domain(Kind::Integer, Field::LongLong, 2, 0, false, null, false), new Domain(Kind::Null, Field::Null)], ['a', 'n']);

        $elements = (new TableQueries($session, $context, new Connection($session->variables, $context)))->elements($plan, ['a' => true]);

        self::assertSame(['n'], array_map(static fn ($element): string => $element->name->column->value, $elements));
    }

    public function testCopiedAnswersTheDefinitionOfTheColumnRead(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (b VARCHAR(10) DEFAULT 'x')");
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $element = (new TableQueries($session, $context, new Connection($session->variables, $context)))->copied(new ColumnOrigin('d', 'u', 't', 'b'), 'c');

        self::assertSame('c', $element?->name->column->value);
    }

    public function testTypeAnswersTheTypeOfAnExpression(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $queries = new TableQueries($session, $context, new Connection($session->variables, $context));
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame(['BIGINT(12)', 'INT(2)', 'VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci', 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci', 'VARBINARY(0)', 'TIME(3)', 'DECIMAL(2,1)'], [
            $queries->type(new Domain(Kind::Integer, Field::LongLong, 12)),
            $queries->type(new Domain(Kind::Integer, Field::LongLong, 2)),
            $queries->type(new Domain(Kind::String, Field::VarString, 512, Domain::NOT_FIXED, false, $collation)),
            $queries->type(new Domain(Kind::String, Field::VarString, 513, Domain::NOT_FIXED, false, $collation)),
            $queries->type(new Domain(Kind::Null, Field::Null)),
            $queries->type(new Domain(Kind::Time, Field::Time, 14, 3)),
            $queries->type(new Domain(Kind::Decimal, Field::NewDecimal, 4, 1)),
        ]);
    }

    public function testIntegerAnswersTheIntegerTypeWithItsDisplayWidth(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $queries = new TableQueries($session, $context, new Connection($session->variables, $context));

        self::assertSame(['TINYINT(4) UNSIGNED', 'MEDIUMINT(9)', 'BIGINT(11)', 'INT(3)'], [
            $queries->integer(new Domain(Kind::Integer, Field::Tiny, 4, 0, true), 'TINYINT'),
            $queries->integer(new Domain(Kind::Integer, Field::Int24, 9), 'MEDIUMINT'),
            $queries->integer(new Domain(Kind::Integer, Field::LongLong, 11), 'INT'),
            $queries->integer(new Domain(Kind::Integer, Field::LongLong, 10, display: 3), 'INT'),
        ]);
    }

    public function testCharacterAnswersTheStringTypeWithItsCollation(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $queries = new TableQueries($session, $context, new Connection($session->variables, $context));
        $collation = Collation::known('latin1_swedish_ci');
        $binary = Collation::known('binary');

        self::assertSame(["ENUM('a','b''c') CHARACTER SET latin1 COLLATE latin1_swedish_ci", 'VARBINARY(10)', 'BLOB', 'MEDIUMTEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci'], [
            $queries->character(new Domain(Kind::String, Field::Enum, 3, Domain::NOT_FIXED, false, $collation, true, ['a', "b'c"])),
            $queries->character(new Domain(Kind::String, Field::VarString, 10, Domain::NOT_FIXED, false, $binary)),
            $queries->character(new Domain(Kind::String, Field::Blob, 65535, Domain::NOT_FIXED, false, $binary)),
            $queries->character(new Domain(Kind::String, Field::Blob, 65536, Domain::NOT_FIXED, false, $collation)),
        ]);
    }

    public function testTextAnswersTheTypeThatHoldsTheBytes(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $queries = new TableQueries($session, $context, new Connection($session->variables, $context));

        self::assertSame(['TINYBLOB', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'], [$queries->text(255, true), $queries->text(65535, false), $queries->text(80000, false), $queries->text(16777216, false)]);
    }

    public function testNullabilityAnswersTheImplicitDefaultOfANotNullColumn(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $queries = new TableQueries($session, $context, new Connection($session->variables, $context));

        self::assertSame([" NOT NULL DEFAULT '0.00'", ' NOT NULL', ''], [
            $queries->nullability(new Domain(Kind::Decimal, Field::NewDecimal, 5, 2, false, null, false)),
            $queries->nullability(new Domain(Kind::DateTime, Field::DateTime, 19, 0, false, null, false)),
            $queries->nullability(new Domain(Kind::Integer, Field::Long, 11)),
        ]);
    }

    public function testFillStoresAValueOutsideTheRangeWithAWarningOutsideAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode = ''");

        $session->query('CREATE TABLE k (a TINYINT NOT NULL) SELECT 300 AS a UNION ALL SELECT NULL');
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT * FROM k')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1264', "Out of range value for column 'a' at row 1"], ['Warning', '1048', "Column 'a' cannot be null"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['127'], ['0']], $rows->rows);
    }

    public function testDefaultedRefusesAColumnWithoutADefaultUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1364);

        $session->query('CREATE TABLE k (z INT NOT NULL) SELECT 1 AS a');
    }

    public function testStoredRefusesNullForANotNullColumnUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1048);

        $session->query('CREATE TABLE k (a INT NOT NULL) SELECT NULL AS a');
    }

    public function testTypeMakesANullColumnABinaryBeforeMySql81(): void
    {
        $older = (new Instance('8.0.44', [], ['d']))->connect('root', 'localhost', 'd');
        $older->query('CREATE TABLE n AS SELECT NULL AS a; CREATE TABLE m AS SELECT NULL AS a UNION SELECT NULL');
        $newer = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $newer->query('CREATE TABLE n AS SELECT NULL AS a');
        $plain = $older->query('SHOW CREATE TABLE n')[0];
        $united = $older->query('SHOW CREATE TABLE m')[0];
        $current = $newer->query('SHOW CREATE TABLE n')[0];

        self::assertInstanceOf(ResultSet::class, $plain);
        self::assertInstanceOf(ResultSet::class, $united);
        self::assertInstanceOf(ResultSet::class, $current);
        self::assertStringContainsString('`a` binary(0) DEFAULT NULL', (string) $plain->rows[0][1]);
        self::assertStringContainsString('`a` binary(0) DEFAULT NULL', (string) $united->rows[0][1]);
        self::assertStringContainsString('`a` varbinary(0) DEFAULT NULL', (string) $current->rows[0][1]);
    }
}
