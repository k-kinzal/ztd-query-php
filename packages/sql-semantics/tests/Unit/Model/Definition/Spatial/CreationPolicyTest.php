<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Spatial\CreationPolicy;

#[CoversClass(CreationPolicy::class)]
#[Medium]
final class CreationPolicyTest extends TestCase
{
    public function testPoliciesKeepReplacementDistinctFromIgnoringAnExistingDefinition(): void
    {
        self::assertNotSame(CreationPolicy::IfNotExists, CreationPolicy::Replace);
        self::assertCount(3, CreationPolicy::cases());
    }

}
