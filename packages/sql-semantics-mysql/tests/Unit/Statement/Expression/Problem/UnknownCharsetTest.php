<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;

#[CoversClass(UnknownCharset::class)]
#[Small]
final class UnknownCharsetTest extends TestCase
{
    public function testMessageNamesWhatTheServerLacks(): void
    {
        self::assertSame("Unknown character set: 'klingon'", (new UnknownCharset('klingon'))->message());
    }
}
