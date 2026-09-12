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
}
