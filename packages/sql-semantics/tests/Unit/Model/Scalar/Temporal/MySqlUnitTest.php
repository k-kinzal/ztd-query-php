<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\Extract;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlUnit::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MySqlUnitTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFields')]
    public function testFieldsRoundTrip(MySqlUnit $field): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('SELECT EXTRACT(' . $field->value . ' FROM CURRENT_TIMESTAMP)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $extract = $query->outputs[0]->expression;
        self::assertInstanceOf(Extract::class, $extract);
        self::assertSame($field, $extract->field);
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    /**
     * @return list<array{MySqlUnit}>
     */
    public static function providerFields(): array
    {
        return array_map(static fn (MySqlUnit $field): array => [$field], MySqlUnit::cases());
    }


    public function testSpelledReadsTheOdbcSpellings(): void
    {
        self::assertSame(MySqlUnit::Day, MySqlUnit::spelled('SQL_TSI_DAY'));
        self::assertSame(MySqlUnit::Quarter, MySqlUnit::spelled('quarter'));
        self::assertNull(MySqlUnit::spelled('SQL_TSI_FORTNIGHT'));
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(d DATETIME)')))->bind('SELECT DATE_ADD(d, INTERVAL 1 SQL_TSI_DAY) FROM t');
        self::assertSame('SELECT DATE_ADD(`d`, INTERVAL 1 DAY) FROM `t`', $query->toString());
    }
}
