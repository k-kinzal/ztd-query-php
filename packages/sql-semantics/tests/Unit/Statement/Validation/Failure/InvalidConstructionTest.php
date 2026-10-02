<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Failure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(InvalidConstruction::class)]
#[Small]
final class InvalidConstructionTest extends TestCase
{
    public function testFailureIsNotASyntaxRejectionOrSuccessfulSemanticResult(): void
    {
        $cause = new RuntimeException('original cause');
        $failure = new InvalidConstruction('A bound input belongs to another context.', 0, $cause);
        self::assertSame($cause, $failure->getPrevious());
        self::assertSame('A bound input belongs to another context.', $failure->getMessage());
    }
}
