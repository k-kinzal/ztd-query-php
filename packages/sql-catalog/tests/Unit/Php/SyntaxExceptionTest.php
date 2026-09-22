<?php

declare(strict_types=1);

namespace Tests\Unit\Php;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlCatalog\Php\SyntaxException;

#[CoversClass(SyntaxException::class)]
final class SyntaxExceptionTest extends TestCase
{
    public function testMessageNamesTheFileAndTheReason(): void
    {
        $exception = new SyntaxException('a.php', 'unexpected token');
        self::assertSame('Cannot parse "a.php": unexpected token.', $exception->getMessage());
        self::assertSame('a.php', $exception->path);
    }

    public function testTheWrappedErrorIsKept(): void
    {
        $previous = new RuntimeException('inner');
        self::assertSame($previous, (new SyntaxException('a.php', 'broken', $previous))->getPrevious());
    }
}
