<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFormatter\FormattingException;

#[CoversClass(FormattingException::class)]
final class FormattingExceptionTest extends TestCase
{
    public function testGetPreviousRetainsVerificationCause(): void
    {
        $cause = new RuntimeException('Invalid token boundary');
        $error = new FormattingException('Verification failed', 0, $cause);
        self::assertSame($cause, $error->getPrevious());
        self::assertSame('Verification failed', $error->getMessage());
    }
}
