<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Core\Literal\DecodingException;

#[CoversClass(DecodingException::class)]
#[Medium]
final class DecodingExceptionTest extends TestCase
{
    public function testPreservesTheReasonAndCause(): void
    {
        $cause = new RuntimeException('invalid token');
        $error = new DecodingException('not a literal', 0, $cause);
        self::assertSame($cause, $error->getPrevious());
        self::assertSame('not a literal', $error->getMessage());
    }

}
