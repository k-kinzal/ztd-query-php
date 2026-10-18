<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\ResolutionException;
use PhpParser\Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolutionException::class)]
#[Small]
final class ResolutionExceptionTest extends TestCase
{
    public function testRetainsTheOriginalDiagnosticAndSourceLocation(): void
    {
        $previous = new Error('Duplicate alias');
        $error = new ResolutionException('Duplicate alias', 'app.php', 3, $previous);
        self::assertSame('app.php', $error->path);
        self::assertSame(3, $error->sourceLine);
        self::assertSame('app.php:3: Duplicate alias', $error->getMessage());
        self::assertSame($previous, $error->getPrevious());
    }
}
