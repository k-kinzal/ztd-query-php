<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Scalar\Intrinsic\ExtractionField;
use SqlSemantics\Model\Scalar\Temporal\PostgreSqlField;

#[CoversClass(ExtractionField::class)]
final class ExtractionFieldTest extends TestCase
{
    #[TestWith(['centuries', PostgreSqlField::Century])]
    #[TestWith(['y', PostgreSqlField::Year])]
    #[TestWith(['microsecond', PostgreSqlField::Microseconds])]
    #[TestWith(['msec', PostgreSqlField::Milliseconds])]
    #[TestWith(['j', PostgreSqlField::Julian])]
    #[TestWith(['timezone_h', PostgreSqlField::TimezoneHour])]
    #[TestWith(['timezone_m', PostgreSqlField::TimezoneMinute])]
    #[TestWith(['mins', PostgreSqlField::Minute])]
    #[TestWith(['isoyear', PostgreSqlField::Isoyear])]
    #[TestWith(['unknown', null])]
    public function testPostgresResolvesUnitAliases(string $name, ?PostgreSqlField $expected): void
    {
        self::assertSame($expected, ExtractionField::postgres($name));
    }
}
