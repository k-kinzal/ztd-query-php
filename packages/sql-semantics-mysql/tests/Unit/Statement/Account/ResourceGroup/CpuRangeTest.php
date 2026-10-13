<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange;

#[CoversClass(CpuRange::class)]
#[Medium]
final class CpuRangeTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int|null}>
     */
    public static function providerInvalidNumbers(): iterable
    {
        yield 'negative start' => [-1, null];
        yield 'overflowing start' => [2147483648, null];
        yield 'negative end' => [0, -1];
        yield 'overflowing end' => [0, 2147483648];
    }

    #[DataProvider('providerInvalidNumbers')]
    public function testRejectsOutOfRangeCpuNumbers(int $first, ?int $last): void
    {
        $this->expectExceptionMessage('A CPU index is an integer from 0 through 2147483647.');

        new CpuRange($first, $last);
    }

    public function testRenderUsesNumericIndicesRegardlessOfInputSpelling(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('ALTER RESOURCE GROUP g VCPU 0001-02147483647');

        self::assertSame('ALTER RESOURCE GROUP g VCPU = 1 - 2147483647', $query->toString());
    }

    public function testRenderWritesNumbersAndRanges(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g VCPU = 0, 2 - 5', (new Semantics(Dialect::MySql))->analyze('alter resource group g vcpu 0 2-5')->toString());
    }
}
