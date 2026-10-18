<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\ParserException;
use PhpParser\Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParserException::class)]
#[Small]
final class ParserExceptionTest extends TestCase
{
    public function testRetainsTheOriginalErrorAndSourceLocation(): void
    {
        $previous = new Error('Unexpected token');
        $error = new ParserException('Unexpected token', 'app.php', 3, $previous);
        self::assertSame('app.php', $error->path);
        self::assertSame(3, $error->sourceLine);
        self::assertSame('app.php:3: Unexpected token', $error->getMessage());
        self::assertSame($previous, $error->getPrevious());
    }
}
