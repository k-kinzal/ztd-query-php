<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\TypeShape as Subject;

#[CoversClass(Subject::class)]
final class TypeShapeTest extends TestCase
{
    public function testStoresDecimalAndSerialMetadata(): void
    {
        $shape = new Subject('NUMERIC', null, 8, 2, true);
        self::assertSame('NUMERIC', $shape->type);
        self::assertNull($shape->length);
        self::assertSame(8, $shape->precision);
        self::assertSame(2, $shape->scale);
        self::assertTrue($shape->autoIncrement);
    }

    public function testFromNumbersReadsOneNumberAsALengthAndTwoAsAPrecisionAndScale(): void
    {
        $length = Subject::fromNumbers('VARCHAR', [30]);
        $decimal = Subject::fromNumbers('DECIMAL', [8], true);
        $both = Subject::fromNumbers('NUMERIC', [10, 2], true);
        $plain = Subject::fromNumbers('TEXT', []);
        $serial = Subject::fromNumbers('BIGINT', [], false, true);

        self::assertSame([30, null, null], [$length->length, $length->precision, $length->scale]);
        self::assertSame([null, 8, 0], [$decimal->length, $decimal->precision, $decimal->scale]);
        self::assertSame([null, 10, 2], [$both->length, $both->precision, $both->scale]);
        self::assertSame(['TEXT', null, null, null], [$plain->type, $plain->length, $plain->precision, $plain->scale]);
        self::assertTrue($serial->autoIncrement);
        self::assertFalse($plain->autoIncrement);
    }
}
