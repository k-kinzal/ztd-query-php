<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Preparation\Predicates;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Predicates::class)]
#[Small]
final class PredicatesTest extends TestCase
{
    public function testCompileRetainsAConstantBooleanOperandAcrossRows(): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->query('INSERT INTO t VALUES(1),(2)');
        $result = $session->query("SELECT a FROM t WHERE CAST('123' AS DECIMAL(1)) OR a>0")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
        self::assertSame([['Warning', 1264, "Out of range value for column '(null)' at row 1"]], $session->diagnostics->conditions);
    }
    public function testCompilePreservesLegacyRootIntegerCoercion(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT 1 FROM DUAL WHERE 'abc'")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: 'abc'"]], $session->diagnostics->conditions);
    }

}
