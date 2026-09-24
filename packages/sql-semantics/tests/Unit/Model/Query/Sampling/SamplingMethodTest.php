<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Sampling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Sampling\SamplingMethod;

#[CoversClass(SamplingMethod::class)]
#[Medium]
final class SamplingMethodTest extends TestCase
{
    public function testNamesTheBuiltInMethodsByKeyword(): void
    {
        self::assertSame(['SYSTEM', 'BERNOULLI'], array_column(SamplingMethod::cases(), 'value'));
    }
}
