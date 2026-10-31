<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Program\Write\UpdateInputs::class)]
#[Small]
final class UpdateInputsTest extends TestCase
{
    public function testScopePreparesDerivedInputsBeforeLegacyWriteCleanup(): void
    {
        $session = (new \MySqlMemory\Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $reply = $session->run('UPDATE t JOIN (SELECT absent) q ON TRUE SET t.a=NOW(9)');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame("Unknown column 'absent' in 'field list'", $reply[0]->getMessage());
        self::assertSame([1054], array_column($session->diagnostics->conditions, 1));
    }

    public function testConditionsRetainsUsingChecksBeforeTheDeferredValue(): void
    {
        $session = (new \MySqlMemory\Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(b INT)');
        $reply = $session->run('UPDATE t JOIN u USING(a) SET t.a=NOW(9)');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1054, $reply[0]->getCode());
        self::assertSame([1054], array_column($session->diagnostics->conditions, 1));
    }

}
