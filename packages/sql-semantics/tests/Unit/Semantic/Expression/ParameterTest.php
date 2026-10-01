<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\Parameter::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ParameterTest extends TestCase
{
    public function testToStringPreservesParameterIdentity(): void
    {
        $parameter = new \SqlSemantics\Semantic\Expression\Parameter(':value');
        self::assertSame(':value', $parameter->toString());
    }
}
