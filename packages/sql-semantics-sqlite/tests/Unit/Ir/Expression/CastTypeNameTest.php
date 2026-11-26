<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTypeName;

#[CoversClass(CastTypeName::class)]
#[Small]
final class CastTypeNameTest extends TestCase
{
    public function testTargetKeepsNativeSubstringPriority(): void
    {
        self::assertSame(CastTarget::Integer, (new CastTypeName('FLOAT/*int*/'))->target());
        self::assertSame(CastTarget::Numeric, (new CastTypeName(''))->target());
    }

    public function testQuotedWritesOneDecodedName(): void
    {
        self::assertSame('"名 ""int"""', (new CastTypeName('名 "int"'))->quoted());
    }

    public function testPayloadBytesCountsUtf8AndEscapes(): void
    {
        $type = new CastTypeName('名 "int"');

        self::assertSame(strlen($type->quoted()), $type->payloadBytes());
    }
}
