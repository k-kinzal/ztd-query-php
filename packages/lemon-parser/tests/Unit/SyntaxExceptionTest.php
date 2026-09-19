<?php

declare(strict_types=1);

namespace Tests\Unit;

use LemonParser\Ast\Location;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyntaxException::class)]
#[UsesClass(Location::class)]
#[Small]
final class SyntaxExceptionTest extends TestCase
{
    public function testGetMessage(): void
    {
        $exception = new SyntaxException('Missing "]" on precedence mark.', new Location(3, 7));

        self::assertSame('Missing "]" on precedence mark. at 3:7', $exception->getMessage());
        self::assertSame('3:7', (string) $exception->location);
    }
}
