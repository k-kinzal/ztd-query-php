<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\CompositionException;

#[CoversClass(CompositionException::class)]
#[Small]
final class CompositionExceptionTest extends TestCase
{
    public function testIsARuntimeFailureWithItsMessage(): void
    {
        $error = new CompositionException('No such form');
        self::assertSame('No such form', $error->getMessage());
        self::assertSame(0, $error->getCode());
    }
}
