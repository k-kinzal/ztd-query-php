<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\IdentityGenerationStrategy;

#[CoversClass(IdentityGenerationStrategy::class)]
final class IdentityGenerationStrategyTest extends TestCase
{
    public function testListsSupportedStrategies(): void
    {
        self::assertSame(
            [IdentityGenerationStrategy::MaxValue, IdentityGenerationStrategy::Sequence],
            IdentityGenerationStrategy::cases(),
        );
    }
}
