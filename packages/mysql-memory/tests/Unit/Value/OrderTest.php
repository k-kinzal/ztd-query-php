<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Order::class)]
#[Small]
final class OrderTest extends TestCase
{
    public function testCompareSortsNullFirst(): void
    {
        $domain = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([0, -1, 1], [Order::compare(null, null, $domain), Order::compare(null, '', $domain), Order::compare('', null, $domain)]);
    }

    public function testCompareFollowsTheCollationOfAString(): void
    {
        $domain = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([0, -1], [Order::compare('a', 'A', $domain), Order::compare('a', 'b', $domain)]);
    }

    public function testCompareFollowsTheValueOfANumber(): void
    {
        self::assertSame([1, 0, -1, -1], [Order::compare(-1, 1, Domain::integer(Field::LongLong, 20, true)), Order::compare('1.0', '1.00', Domain::decimal(5, 2)), Order::compare('-2', '1.5', Domain::decimal(5, 2)), Order::compare(1.5, 2.5, Domain::double())]);
    }

    public function testCompareOrdersTimesByTheirLength(): void
    {
        $domain = new Domain(Kind::Time, Field::Time, 10);

        self::assertSame([-1, 1, -1], [Order::compare('-01:00:00', '00:30:00', $domain), Order::compare('100:00:00', '20:00:00', $domain), Order::compare('-02:00:00', '-01:00:00', $domain)]);
    }

    public function testCompareOrdersDatesByTheirText(): void
    {
        $domain = new Domain(Kind::Date, Field::Date, 10);

        self::assertSame([-1, 0], [Order::compare('2024-01-02', '2024-01-10', $domain), Order::compare('2024-01-02', '2024-01-02', $domain)]);
    }

    public function testKeyIsEqualForValuesThatCompareEqual(): void
    {
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));
        $decimal = Domain::decimal(5, 2);

        self::assertSame([true, true, false], [Order::key('a', $string) === Order::key('A', $string), Order::key('1.50', $decimal) === Order::key('1.5', $decimal), Order::key('a', $string) === Order::key('a ', $string)]);
    }

    public function testKeyWritesEachKindOfValue(): void
    {
        self::assertSame(["\0N", 'i18446744073709551615', 'd1.5', 'f0', 'f1.5', 't2024-01-01'], [Order::key(null, Domain::integer()), Order::key(-1, Domain::integer(Field::LongLong, 20, true)), Order::key('1.50', Domain::decimal(5, 2)), Order::key(-0.0, Domain::double()), Order::key(1.5, Domain::double()), Order::key('2024-01-01', new Domain(Kind::Date, Field::Date, 10))]);
    }

    public function testDecimalDropsTrailingZerosAfterThePoint(): void
    {
        self::assertSame(['1.5', '0', '10', '-2'], [Order::decimal('1.500'), Order::decimal('-0.000'), Order::decimal('10'), Order::decimal('-2.0')]);
    }

    public function testTimeAnswersTheMicrosecondsOfATime(): void
    {
        self::assertSame([-5400500000.0, 3020399000000.0, 0.0], [Order::time('-01:30:00.5'), Order::time('838:59:59'), Order::time('00:00:00')]);
    }

    public function testJsonOrdersByTheJsonOrderAndContainersBySize(): void
    {
        $json = new Domain(Kind::Json, Field::Json, 4294967295, 31, false, Collation::known('utf8mb4_bin'));

        self::assertSame([-1, 0, -1, 1], [Order::json('null', '1'), Order::json('1', '1.0'), Order::json('[1]', '[0, 5]'), Order::compare('"a"', '2', $json)]);
        self::assertSame(Order::key('1', $json), Order::key('1.0', $json));
    }
}
