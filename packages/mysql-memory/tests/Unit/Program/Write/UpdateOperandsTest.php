<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Write;

use MySqlMemory\Instance;
use MySqlMemory\Program\Write\UpdateOperands;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateOperands::class)]
#[Small]
final class UpdateOperandsTest extends TestCase
{
    public function testPrepareUsesTheModernValuePhaseBeforeOrdering(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectExceptionMessage('Operand should contain 1 column(s)');
        $session->query('UPDATE t SET a=ROW(1,2) ORDER BY missing');
    }

    public function testOrderingUsesTheLegacyPhaseBeforeAssignmentValues(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectExceptionMessage("Unknown column 'missing' in 'order clause'");
        $session->query('UPDATE t SET a=ROW(1,2) ORDER BY missing');
    }

    public function testDestinationsBindBeforeTheLegacyMultipleTableJoin(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(b INT)');
        $this->expectExceptionMessage("Unknown column 'missing' in 'field list'");
        $session->query('UPDATE t JOIN u ON absent SET missing=ROW(1,2)');
    }

    public function testDestinationsPrecedeTheLegacyMultipleTableLimit(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(b INT)');
        $this->expectExceptionMessage("Unknown column 'missing' in 'field list'");
        $session->query('UPDATE t,u SET missing=1 LIMIT 1');
    }

    public function testOperandResolvesTheRetainedRowBeforeReportingItsWidth(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectExceptionMessage("Unknown column 'missing' in 'where clause'");
        $session->query('UPDATE t SET a=1 WHERE ROW(missing,2)');
    }
    public function testValuesBindsPreparedParametersBeforeThePrecisionCondition(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $reply = $session->run("PREPARE st FROM 'UPDATE t,u SET t.a=?+NOW(9)'");
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1426, $reply[0]->getCode());
    }

    public function testPrepareRetainsTheWritesCommonTableDefinitions(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectExceptionMessage('SELECT list and column names list have different column counts');
        $session->query('WITH c AS(SELECT 1),q(x,y) AS(SELECT * FROM c) UPDATE t JOIN q SET a=1');
    }

}
