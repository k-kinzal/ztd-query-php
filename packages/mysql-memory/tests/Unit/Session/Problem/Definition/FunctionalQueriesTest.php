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
        yield 'missing subquery table' => ['CREATE TABLE t(a INT,INDEX i(missing,((TABLE absent))))', 1146, "Table 'd.absent' doesn't exist"];
        yield 'existing subquery table' => ['CREATE TABLE t(a INT,INDEX i(missing,((TABLE present))))', 3758, "Expression of functional index 'i' contains a disallowed function."];
        yield 'unnamed index' => ['CREATE TABLE t(a INT,INDEX(missing,((TABLE present))))', 3758, "Expression of functional index 'functional_index' contains a disallowed function."];
        yield 'constraint name' => ['CREATE TABLE t(a INT,CONSTRAINT c UNIQUE(missing,((TABLE present))))', 3758, "Expression of functional index 'c' contains a disallowed function."];
        yield 'target checked later' => ['CREATE TABLE IF NOT EXISTS present(a INT,INDEX i(((TABLE absent))))', 1146, "Table 'd.absent' doesn't exist"];
        yield 'existing target before expression' => ['CREATE TABLE present(a INT,INDEX i(missing,((TABLE present))))', 1050, "Table 'present' already exists"];
        yield 'missing target database before expression' => ['CREATE TABLE absent_db.t(a INT,INDEX i(missing,((TABLE present))))', 1049, "Unknown database 'absent_db'"];
        yield 'projection checked later' => ['CREATE TABLE t(a INT,INDEX i(((SELECT unknown FROM present))))', 3758, "Expression of functional index 'i' contains a disallowed function."];
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
    public function testForbiddenReportsTheIndexNameBeforeMissingKeyColumns(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE TABLE t(a INT,INDEX i(missing,((TABLE present))))')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $statement);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3758);
        $this->expectExceptionMessage("Expression of functional index 'i' contains a disallowed function.");

        (new FunctionalQueries())->forbidden($statement);
    }

}
