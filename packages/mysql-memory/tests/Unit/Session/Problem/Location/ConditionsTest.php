<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Location\Conditions;
use MySqlMemory\Session\Problems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Conditions::class)]
#[Small]
final class ConditionsTest extends TestCase
{
    public function testCheckDefersBothStoredCallAndColumnDependenciesInLegacyPreparation(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->query('CREATE TABLE u(a INT)');
        $operation = $session->analyze('UPDATE t,u SET t.a=missing_function(missing_column)');
        $calls = (new Problems())->calls($operation);
        self::assertCount(1, $calls);
        self::assertCount(1, $operation->facts->diagnostics);

        (new Conditions())->check($operation, $session, $calls, $operation->facts->diagnostics, true);

        self::assertSame([], $session->diagnostics->conditions);
    }

    #[TestWith([false, "Unknown column 'missing_filter' in 'where clause'"])]
    #[TestWith([true, "Unknown column 'missing_value' in 'field list'"])]
    public function testCheckUsesTheNativePreparedAndExecutionOrderInMySql57(bool $prepared, string $message): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->query('CREATE TABLE u(a INT)');
        $operation = $session->analyze('UPDATE t,u SET t.a=missing_value WHERE missing_filter');
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage($message);

        (new Conditions())->check($operation, $session, [], $operation->facts->diagnostics, $prepared);
    }
}
