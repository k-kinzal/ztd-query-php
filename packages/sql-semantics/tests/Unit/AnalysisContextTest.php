<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisContext;

#[CoversClass(AnalysisContext::class)]
#[Small]
final class AnalysisContextTest extends TestCase
{
    public function testContractIsAvailableOnItsOwn(): void
    {
        self::assertTrue(interface_exists(AnalysisContext::class));
    }
}
