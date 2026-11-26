<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ParameterTarget;

#[CoversClass(ParameterTarget::class)]
#[Small]
final class ParameterTargetTest extends TestCase
{
    public function testCasesRepresentBothNativeIntegralCasts(): void
    {
        self::assertSame(['SIGNED', 'UNSIGNED'], array_column(ParameterTarget::cases(), 'value'));
    }
}
