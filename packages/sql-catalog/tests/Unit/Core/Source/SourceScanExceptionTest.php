<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Source\SourceScanException;

#[CoversClass(SourceScanException::class)]
final class SourceScanExceptionTest extends TestCase
{
    public function testMessageNamesThePathAndTheReason(): void
    {
        $exception = new SourceScanException('missing', 'no such file or directory');
        self::assertSame('Cannot read "missing": no such file or directory.', $exception->getMessage());
        self::assertSame('missing', $exception->path);
    }
}
