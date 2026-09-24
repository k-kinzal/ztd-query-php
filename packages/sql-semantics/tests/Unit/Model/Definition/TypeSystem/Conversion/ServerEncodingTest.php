<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding;

#[CoversClass(ServerEncoding::class)]
final class ServerEncodingTest extends TestCase
{
    public function testSpellsTheCanonicalServerNames(): void
    {
        self::assertCount(42, ServerEncoding::cases());
        self::assertSame(ServerEncoding::ShiftJis2004, ServerEncoding::from('SHIFT_JIS_2004'));
        self::assertSame('ISO_8859_5', ServerEncoding::Iso88595->value);
    }
}
