<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ConfigurationException::class)]
final class ConfigurationExceptionTest extends TestCase
{
    public function testGetMessageAndPreviousRetainTheDiagnosticAndCause(): void
    {
        $cause = new RuntimeException('cause');
        $error = new ConfigurationException('diagnostic', previous: $cause);
        self::assertSame('diagnostic', $error->getMessage());
        self::assertSame($cause, $error->getPrevious());
    }
}
