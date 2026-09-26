<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Facade\InvalidConfigurationException;

#[CoversClass(InvalidConfigurationException::class)]
final class InvalidConfigurationExceptionTest extends TestCase
{
    public function testPreservesTheConfigurationError(): void
    {
        self::assertSame('Invalid configuration', (new InvalidConfigurationException('Invalid configuration'))->getMessage());
    }
}
