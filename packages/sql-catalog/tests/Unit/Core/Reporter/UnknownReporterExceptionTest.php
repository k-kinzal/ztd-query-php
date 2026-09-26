<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Reporter\UnknownReporterException;

#[CoversClass(UnknownReporterException::class)]
final class UnknownReporterExceptionTest extends TestCase
{
    public function testMessageNamesWhatIsAvailable(): void
    {
        $exception = new UnknownReporterException('xml', ['json', 'text']);
        self::assertSame('Unknown reporter "xml". Available reporters: json, text.', $exception->getMessage());
        self::assertSame('xml', $exception->name);
    }

    public function testMessageSaysSoWhenNothingIsRegistered(): void
    {
        self::assertStringContainsString('none', (new UnknownReporterException('json', []))->getMessage());
    }
}
