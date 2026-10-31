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
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

#[CoversClass(CpuRange::class)]
#[Medium]
final class CpuRangeTest extends TestCase
{
    /**
     * @return iterable<string, array{Numeral, Numeral|null}>
     */
    public static function providerInvalidNumbers(): iterable
    {
        yield 'fractional start' => [new Numeral('1.5'), null];
        yield 'overflowing start' => [new Numeral('2147483648'), null];
        yield 'exponent end' => [new Numeral('0'), new Numeral('1e0')];
        yield 'overflowing end' => [new Numeral('0'), new Numeral('2147483648')];
    }

    #[DataProvider('providerInvalidNumbers')]
    public function testRejectsOutOfRangeCpuNumbers(Numeral $first, ?Numeral $last): void
    {
        $this->expectExceptionMessage('A CPU index is an integer from 0 through 2147483647.');

        new CpuRange($first, $last);
    }

    public function testRenderPreservesNumericOperandSpelling(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('ALTER RESOURCE GROUP g VCPU 0001-02147483647');

        self::assertSame('ALTER RESOURCE GROUP g VCPU 0001 - 02147483647', $query->toString());
    }

    public function testRenderWritesNumbersAndRanges(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g VCPU 0 2 - 5', (new Semantics(Dialect::MySql))->analyze('alter resource group g vcpu 0 2-5')->toString());
    }
}
