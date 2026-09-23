<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Model\Definition\Routine\AggregateInputMode::class)]
final class AggregateInputModeTest extends TestCase
{
    public function testImplicitModeRemainsDistinctFromExplicitInput(): void
    {
        self::assertNotSame(\SqlSemantics\Model\Definition\Routine\AggregateInputMode::Implicit, \SqlSemantics\Model\Definition\Routine\AggregateInputMode::Input);
        self::assertSame('VARIADIC', \SqlSemantics\Model\Definition\Routine\AggregateInputMode::Variadic->value);
    }

}
