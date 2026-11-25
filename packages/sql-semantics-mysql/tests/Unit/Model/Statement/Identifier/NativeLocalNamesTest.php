<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;

#[CoversClass(NativeLocalNames::class)]
#[Small]
final class NativeLocalNamesTest extends TestCase
{
    public function testKeyUsesNativeSimpleCaseWithoutDroppingAccents(): void
    {
        $names = new NativeLocalNames();

        self::assertSame('kiéa ', $names->key('KİÉA '));
        self::assertNotSame($names->key('É'), $names->key('e'));
        self::assertNotSame($names->key("e\u{0301}"), $names->key('é'));
    }

    public function testPrefixKeepsTheIncompleteNativeSuffix(): void
    {
        $names = new NativeLocalNames();
        $raw = str_repeat('漢', 85) . "\xe6";

        self::assertSame($raw, $names->prefix(" \t" . str_repeat('漢', 90)));
    }

    public function testReadableRequiresCompleteUtf8AcrossTheNativeBoundary(): void
    {
        $names = new NativeLocalNames();

        self::assertTrue($names->readable(str_repeat('漢', 85) . 'x'));
        self::assertFalse($names->readable(str_repeat('漢', 86)));
    }
}
