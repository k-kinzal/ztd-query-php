<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZtdQuery\Platform\ValueRenderer;

#[CoversNothing]
final class ValueRendererTest extends TestCase
{
    public function testDeclaresTypedValueRenderingContract(): void
    {
        $reflection = new ReflectionClass(ValueRenderer::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('renderValue'));
    }
}
