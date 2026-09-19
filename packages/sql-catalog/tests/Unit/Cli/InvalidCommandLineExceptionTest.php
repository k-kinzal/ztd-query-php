<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\InvalidCommandLineException;

#[CoversClass(InvalidCommandLineException::class)]
final class InvalidCommandLineExceptionTest extends TestCase
{
    public function testMessageIsTheReasonItWasGiven(): void
    {
        self::assertSame('Unknown option "--x".', (new InvalidCommandLineException('Unknown option "--x".'))->getMessage());
    }
}
