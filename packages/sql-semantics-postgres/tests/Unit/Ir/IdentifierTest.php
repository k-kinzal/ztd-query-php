<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;

#[CoversClass(Identifier::class)]
#[Small]
final class IdentifierTest extends TestCase
{
    public function testQuotedPreservesTheCorrectBytes(): void
    {
        $identifier = new Identifier('ÄA"B');
        self::assertSame('"ÄA""B"', $identifier->quoted());
    }

    public function testRejectsNul(): void
    {
        $this->expectExceptionMessage('without NUL');

        new Identifier("a\0b");
    }
}
