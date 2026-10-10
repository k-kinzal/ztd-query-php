<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Literal\NameBytes;

#[CoversClass(NameBytes::class)]
#[Small]
final class NameBytesTest extends TestCase
{
    public function testFormatKeepsBmpCharactersAndReplacesUnsupportedBytes(): void
    {
        self::assertSame(['é', '????', '?a', '???'], [NameBytes::format('é'), NameBytes::format('😀'), NameBytes::format("\xC3a"), NameBytes::format("\xED\xB0\x80")]);
    }

    public function testFormatStopsAtANulOrIncompleteSequenceAfterLeadingControls(): void
    {
        self::assertSame(['a', 'a', '', 'a ', 'a'], [NameBytes::format("\0a\0b"), NameBytes::format("a\xE9b"), NameBytes::format("\xE9a"), NameBytes::format(' a '), NameBytes::format("\xFFa")]);
    }
}
