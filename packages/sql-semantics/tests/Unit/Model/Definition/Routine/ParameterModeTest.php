<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Model\Definition\Routine\ParameterMode::class)]
final class ParameterModeTest extends TestCase
{
    public function testImplicitModeRemainsDistinctFromExplicitInput(): void
    {
        self::assertNotSame(\SqlSemantics\Model\Definition\Routine\ParameterMode::Implicit, \SqlSemantics\Model\Definition\Routine\ParameterMode::Input);
        self::assertSame('VARIADIC', \SqlSemantics\Model\Definition\Routine\ParameterMode::Variadic->value);
    }

}
