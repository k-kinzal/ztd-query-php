<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Sampling;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

#[CoversClass(Sampling::class)]
#[Small]
final class SamplingTest extends TestCase
{
    public function testOpenedRefusesSamplingInAnUpdateBeforeItsColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6033);
        $this->expectExceptionMessage("'TABLESAMPLE' is not supported");

        $session->query('UPDATE t SET zz = (SELECT 1 FROM t AS x TABLESAMPLE SYSTEM (5))');
    }

    public function testOpenedRefusesSamplingAViewBeforeThePercentage(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT * FROM t');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6108);
        $this->expectExceptionMessage('Tablesample can be applied only on base tables.');

        $session->query('SELECT * FROM v TABLESAMPLE SYSTEM (500)');
    }

    public function testOpenedRefusesAPercentageAbove100BeforeAnyColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6107);
        $this->expectExceptionMessage('Tablesample percentage should range between 0 and 100.');

        $session->query('SELECT zz FROM t TABLESAMPLE SYSTEM (100.0000001)');
    }

    public function testOptimizedRefusesASampledQueryBeforeItRuns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3889);
        $this->expectExceptionMessage('Secondary engine operation failed. Reason: "No secondary engine defined for at least one of the query tables".');

        $session->query('SELECT * FROM t TABLESAMPLE BERNOULLI (100) WHERE (SELECT 1 UNION SELECT 2)');
    }

    public function testUnenginedNamesTheFirstBaseTableIn91(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT 1 UNION SELECT * FROM t TABLESAMPLE SYSTEM (5)');

        self::assertSame('Secondary engine operation failed. Reason: "You have not defined the secondary engine for at least one of the query tables [d.t].".', (new Sampling())->unengined($operation->statement, $operation->facts, $session->settings(), $session->instance->dictionary)->getMessage());
    }

    public function testUnenginedNamesNoTableIn91WhenTheFirstIsDerived(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT * FROM (SELECT 1) AS x, t TABLESAMPLE SYSTEM (5)');

        self::assertSame('Secondary engine operation failed. Reason: "You have not defined the secondary engine for at least one of the query tables.".', (new Sampling())->unengined($operation->statement, $operation->facts, $session->settings(), $session->instance->dictionary)->getMessage());
    }

    public function testBaseAnswersFalseForACommonTableExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $operation = $session->analyze('WITH c AS (SELECT 1) SELECT * FROM c');
        $reference = (new Walker())->find($operation->statement, TableReference::class)[0];

        self::assertFalse((new Sampling())->base($reference, $operation->facts, $session->settings(), $session->instance->dictionary));
    }

    public function testBaseAnswersTrueForASystemTableOfMysql(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT * FROM mysql.db');
        $reference = (new Walker())->find($operation->statement, TableReference::class)[0];

        self::assertTrue((new Sampling())->base($reference, $operation->facts, $session->settings(), $session->instance->dictionary));
    }
}
