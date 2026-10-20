<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Definition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Definition\FunctionalQueries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FunctionalQueries::class)]
#[Small]
final class FunctionalQueriesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerPreparation(): iterable
    {
        yield 'missing indexed target' => ['CREATE INDEX i ON missing (((TABLE absent)))', 1146, "Table 'd.missing' doesn't exist"];
        yield 'missing altered target' => ['ALTER TABLE missing ADD INDEX i (((TABLE absent)))', 1146, "Table 'd.missing' doesn't exist"];
        yield 'indexed target present' => ['CREATE INDEX i ON present (((TABLE absent)))', 1146, "Table 'd.absent' doesn't exist"];
        yield 'explicit widths before indexed target' => ['CREATE INDEX i ON missing (((1,2)=(3,4,5) AND EXISTS(TABLE absent)))', 1241, 'Operand should contain 2 column(s)'];
        yield 'missing subquery table' => ['CREATE TABLE t(a INT,INDEX i(missing,((TABLE absent))))', 1146, "Table 'd.absent' doesn't exist"];
        yield 'existing subquery table' => ['CREATE TABLE t(a INT,INDEX i(missing,((TABLE present))))', 3758, "Expression of functional index 'i' contains a disallowed function."];
        yield 'unnamed index' => ['CREATE TABLE t(a INT,INDEX(missing,((TABLE present))))', 3758, "Expression of functional index 'functional_index' contains a disallowed function."];
        yield 'constraint name' => ['CREATE TABLE t(a INT,CONSTRAINT c UNIQUE(missing,((TABLE present))))', 3758, "Expression of functional index 'c' contains a disallowed function."];
        yield 'target checked later' => ['CREATE TABLE IF NOT EXISTS present(a INT,INDEX i(((TABLE absent))))', 1146, "Table 'd.absent' doesn't exist"];
        yield 'existing target before expression' => ['CREATE TABLE present(a INT,INDEX i(missing,((TABLE present))))', 1050, "Table 'present' already exists"];
        yield 'missing target database before expression' => ['CREATE TABLE absent_db.t(a INT,INDEX i(missing,((TABLE present))))', 1049, "Unknown database 'absent_db'"];
        yield 'projection checked later' => ['CREATE TABLE t(a INT,INDEX i(((SELECT unknown FROM present))))', 3758, "Expression of functional index 'i' contains a disallowed function."];
        yield 'primary key before subquery refusal' => ['CREATE TABLE t(a INT,PRIMARY KEY (((SELECT 1))))', 3756, 'The primary key cannot be a functional index'];
        yield 'explicit row widths' => ['CREATE TABLE t(a INT,INDEX i(((1,2)=(3,4,5) AND EXISTS(TABLE absent))))', 1241, 'Operand should contain 2 column(s)'];
    }

    #[DataProvider('providerPreparation')]
    public function testPrepareUsesResolvedReferencesBeforeIndexValidation(string $sql, int $code, string $message): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE present(a INT)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);

        $session->query($sql);
    }
    public function testDeferredKeepsIndexedConversionsWithTheirIndexOwner(): void
    {
        $session = (new Instance())->connect();
        $indexed = $session->analyze("CREATE TABLE t(a INT,PRIMARY KEY ((CAST(a AT TIME ZONE 'text' AS DATETIME))))");
        $selected = $session->analyze("SELECT CAST(NULL AT TIME ZONE 'text' AS DATETIME)");
        $owned = array_values(array_filter($indexed->facts->diagnostics, static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion));
        self::assertCount(1, $owned);
        self::assertTrue((new FunctionalQueries())->deferred($owned[0], $indexed->statement));
        self::assertFalse((new FunctionalQueries())->deferred($selected->facts->diagnostics[0], $selected->statement));
    }

    public function testForbiddenReportsTheIndexNameBeforeMissingKeyColumns(): void
    {
        $session = (new Instance())->connect();
        $routine = $session->analyze('CREATE PROCEDURE p() CREATE TABLE t(a INT,INDEX i(missing,((TABLE present))))')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $routine);
        $statement = $routine->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable::class, $statement);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3758);
        $this->expectExceptionMessage("Expression of functional index 'i' contains a disallowed function.");

        (new FunctionalQueries())->forbidden($statement);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerAdded(): iterable
    {
        yield 'conversion before missing key column' => ["CREATE INDEX i ON present(missing,(CAST(FALSE AT TIME ZONE 't' AS DATETIME)))", 3998];
        yield 'conversion before repeated key column' => ["CREATE INDEX i ON present(a,a,(CAST(FALSE AT TIME ZONE 't' AS DATETIME)))", 3998];
        yield 'primary before conversion' => ["ALTER TABLE present ADD PRIMARY KEY((CAST(FALSE AT TIME ZONE 't' AS DATETIME)))", 3756];
        yield 'subquery before missing key column' => ['CREATE INDEX i ON present(missing,((SELECT 1)))', 3758];
        yield 'missing functional column before conversion' => ["CREATE INDEX i ON present((CAST(missing AT TIME ZONE 't' AS DATETIME)))", 1054];
        yield 'forbidden function before conversion' => ["CREATE INDEX i ON present(missing,(CAST(CURRENT_TIMESTAMP AT TIME ZONE 't' AS DATETIME)))", 3758];
    }

    #[DataProvider('providerAdded')]
    public function testAddedPreparesIndexedExpressionsBeforeLayoutValidation(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE present(a INT)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);

        $session->query($sql);
    }

    public function testRowsPrecedesUnknownColumnsInsideTheRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectExceptionCode(3800);
        $this->expectExceptionMessage("Expression of functional index 'i' cannot refer to a row value.");
        $session->query('CREATE INDEX i ON t ((ROW(missing,2)))');
    }

}
