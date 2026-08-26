<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\UnsupportedDriverException;

#[CoversClass(UnsupportedDriverException::class)]
final class UnsupportedDriverExceptionTest extends TestCase
{
    public function testNamedSaysWhichDriverWasAskedFor(): void
    {
        self::assertSame('Unsupported driver: oracle', UnsupportedDriverException::named('oracle')->getMessage());
    }

    public function testUndetectableSaysTheConnectionWouldNotSay(): void
    {
        self::assertSame('Unable to detect PDO driver', UnsupportedDriverException::undetectable()->getMessage());
    }
}
