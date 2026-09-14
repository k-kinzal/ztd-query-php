<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator\Reflection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\Reflection\ConversionTarget;

#[CoversClass(ConversionTarget::class)]
final class ConversionTargetTest extends TestCase
{
    public function testOnlyExplicitConversionTypesHaveCases(): void
    {
        self::assertSame(ConversionTarget::Integer, ConversionTarget::tryFrom('int'));
        self::assertSame(ConversionTarget::Float, ConversionTarget::tryFrom('float'));
        self::assertSame(ConversionTarget::String, ConversionTarget::tryFrom('string'));
        self::assertSame(ConversionTarget::Boolean, ConversionTarget::tryFrom('bool'));
        self::assertSame(ConversionTarget::Array, ConversionTarget::tryFrom('array'));
    }
}
