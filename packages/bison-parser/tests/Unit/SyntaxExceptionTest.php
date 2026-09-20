<?php

declare(strict_types=1);

namespace Tests\Unit;

use BisonParser\Ast\Location;
use BisonParser\SyntaxException;
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
        $exception = new SyntaxException('Something is off', new Location(3, 7));

        self::assertSame('Something is off at 3:7', $exception->getMessage());
        self::assertSame('3:7', (string) $exception->location);
    }

    public function testUnexpected(): void
    {
        self::assertSame("Expected a rule but found ';' at 1:2", SyntaxException::unexpected('a rule', "';'", new Location(1, 2))->getMessage());
    }

    public function testUnterminated(): void
    {
        self::assertSame("Missing '\"' closing the string opened at 4:5", SyntaxException::unterminated('string', '"', new Location(4, 5))->getMessage());
    }

    public function testInvalid(): void
    {
        self::assertSame('Invalid directive: %foo at 2:1', SyntaxException::invalid('invalid directive: %foo', new Location(2, 1))->getMessage());
    }
}
