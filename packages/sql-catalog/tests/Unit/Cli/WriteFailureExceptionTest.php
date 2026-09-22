<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\WriteFailureException;

#[CoversClass(WriteFailureException::class)]
final class WriteFailureExceptionTest extends TestCase
{
    public function testMessageNamesThePathAndTheReason(): void
    {
        $exception = new WriteFailureException('out/catalog.json', 'the file could not be written');
        self::assertSame('Cannot write "out/catalog.json": the file could not be written.', $exception->getMessage());
        self::assertSame('out/catalog.json', $exception->path);
    }
}
