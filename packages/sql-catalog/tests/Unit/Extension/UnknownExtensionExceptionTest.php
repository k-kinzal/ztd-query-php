<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\UnknownExtensionException;

#[CoversClass(UnknownExtensionException::class)]
final class UnknownExtensionExceptionTest extends TestCase
{
    public function testMessageNamesWhatIsAvailable(): void
    {
        $exception = new UnknownExtensionException('symfony', ['pdo', 'mysqli']);
        self::assertSame('Unknown extension "symfony". Available extensions: pdo, mysqli.', $exception->getMessage());
        self::assertSame('symfony', $exception->name);
    }

    public function testMessageSaysSoWhenNothingIsRegistered(): void
    {
        self::assertStringContainsString('none', (new UnknownExtensionException('pdo', []))->getMessage());
    }
}
