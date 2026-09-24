<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ResourceLimit::class)]
#[Medium]
final class ResourceLimitTest extends TestCase
{
    public function testKeepsAZeroLimitThatRemovesTheRestriction(): void
    {
        $limit = new ResourceLimit(ResourceLimitKind::ConnectionsPerHour, 0);
        self::assertSame(ResourceLimitKind::ConnectionsPerHour, $limit->kind);
        self::assertSame(0, $limit->value);
    }

    public function testRejectsANegativeCount(): void
    {
        $this->expectException(InvalidStructure::class);
        new ResourceLimit(ResourceLimitKind::QueriesPerHour, -5);
    }
}
