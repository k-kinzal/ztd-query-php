<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(Builtin::class)]
#[Small]
final class BuiltinTest extends TestCase
{
    public function testNameIsTheDisplayedName(): void
    {
        self::assertSame('integer', Builtin::Int4->name());
        self::assertSame('timestamp with time zone', Builtin::Timestamptz->name());
        self::assertSame('"char"', Builtin::Char->name());
        self::assertSame('uuid', Builtin::Uuid->name());
    }
}
