<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\Histogram;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Maintenance\Histogram\BucketCount;

#[CoversClass(BucketCount::class)]
#[Medium]
final class BucketCountTest extends TestCase
{
    #[TestWith([1])]
    #[TestWith([1024])]
    public function testAcceptsTheSupportedBoundaryValues(int $value): void
    {
        self::assertSame($value, (new BucketCount($value))->value);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    #[TestWith([1025])]
    public function testRejectsLimitsOutsideTheBucketDomain(int $value): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new BucketCount($value);
    }
}
