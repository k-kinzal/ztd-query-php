<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Text\NameEncoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(NameEncoding::class)]
#[Small]
final class NameEncodingTest extends TestCase
{
    public function testDecodeInterpretsLatin1BytesAndKeepsUtf8Names(): void
    {
        $encoding = new NameEncoding();
        self::assertSame("Ã©\u{20ac}\u{81}\u{9d}", $encoding->decode("é\x80\x81\x9d", Charset::known('latin1')));
        self::assertSame('é', $encoding->decode('é', Charset::known('utf8mb4')));
        self::assertSame('é', $encoding->decode('é', null));
    }

    public function testEncodeRoundTripsEveryLatin1ByteIncludingUndefinedControls(): void
    {
        $encoding = new NameEncoding();
        $latin1 = Charset::known('latin1');
        $bytes = implode('', array_map(chr(...), range(0, 255)));
        self::assertSame($bytes, $encoding->encode($encoding->decode($bytes, $latin1), $latin1));
        self::assertSame('é', $encoding->encode('é', Charset::known('utf8mb4')));
        self::assertSame('é', $encoding->encode('é', null));
    }
}
