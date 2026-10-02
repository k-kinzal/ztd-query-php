<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Failure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Statement\Validation\Failure\ResourceLimitExceeded;

#[CoversClass(ResourceLimitExceeded::class)]
#[Small]
final class ResourceLimitExceededTest extends TestCase
{
    public function testFailureIsNotASyntaxRejectionOrSuccessfulSemanticResult(): void
    {
        $cause = new RuntimeException('original cause');
        $failure = new ResourceLimitExceeded('The configured construction memory limit was reached.', 0, $cause);
        self::assertSame($cause, $failure->getPrevious());
        self::assertSame('The configured construction memory limit was reached.', $failure->getMessage());
    }
}
