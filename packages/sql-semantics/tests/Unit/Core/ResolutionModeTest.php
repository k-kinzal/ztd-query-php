<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\ResolutionMode;

#[CoversClass(ResolutionMode::class)]
#[Medium]
final class ResolutionModeTest extends TestCase
{
    public function testCasesDistinguishStrictAndPartialResolution(): void
    {
        self::assertSame(['Strict', 'Partial'], array_column(ResolutionMode::cases(), 'name'));
    }

}
