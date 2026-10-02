<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Failure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Statement\Validation\Failure\ImplementationGap;

#[CoversClass(ImplementationGap::class)]
#[Small]
final class ImplementationGapTest extends TestCase
{
    public function testFailureIsNotASyntaxRejectionOrSuccessfulSemanticResult(): void
    {
        $cause = new RuntimeException('original cause');
        $failure = new ImplementationGap('The selected production has no semantic rule.', 0, $cause);
        self::assertSame($cause, $failure->getPrevious());
        self::assertSame('The selected production has no semantic rule.', $failure->getMessage());
    }
}
