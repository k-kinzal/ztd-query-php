<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Temporal\TimestampBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TimestampBinder::class)]
#[Medium]
final class TimestampBinderTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindKeepsTheUnitOfEachForm(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(d DATETIME, e DATE)'));
        $query = $binder->bind('SELECT TIMESTAMPADD(DAY, 1, d), TIMESTAMPDIFF(SQL_TSI_MONTH, d, e) FROM t');
        self::assertSame('SELECT TIMESTAMPADD(DAY, 1, `d`), TIMESTAMPDIFF(MONTH, `d`, `e`) FROM `t`', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testBindRejectsARowOperand(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TemporalOperand->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT TIMESTAMPDIFF(DAY, ROW(1, 2), '2020-01-01')");
    }

    public function testBindLeavesOtherFunctionsAlone(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT DATE_ADD('2020-01-01', INTERVAL 1 DAY)");
        self::assertSame("SELECT DATE_ADD('2020-01-01', INTERVAL 1 DAY)", $query->toString());
    }
}
