<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind;

#[CoversClass(UnaryKind::class)]
#[Small]
final class UnaryKindTest extends TestCase
{
    public function testProvidesTheNativePrefixTokens(): void
    {
        self::assertSame('+', UnaryKind::Plus->value);
        self::assertSame('NOT', UnaryKind::Not->value);
        self::assertSame('~', UnaryKind::Complement->value);
    }
}
