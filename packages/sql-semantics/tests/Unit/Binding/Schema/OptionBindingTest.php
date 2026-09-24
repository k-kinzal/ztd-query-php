<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Schema\OptionBinding;

#[CoversClass(OptionBinding::class)]
#[Medium]
final class OptionBindingTest extends TestCase
{
    public function testStringReturnsScalarsOrNull(): void
    {
        self::assertSame('x', OptionBinding::string(['a' => 'x'], 'a'));
        self::assertNull(OptionBinding::string([], 'a'));
    }

    public function testIntegerParsesDigitsOrReturnsNull(): void
    {
        self::assertSame(42, OptionBinding::integer(['a' => '42'], 'a'));
        self::assertSame(-1, OptionBinding::integer(['a' => '-1'], 'a'));
        self::assertNull(OptionBinding::integer([], 'a'));
    }

    public function testQualifiedBuildsNamesFromStringsAndParts(): void
    {
        self::assertSame(['s', 'n'], OptionBinding::qualified(['a' => ['s', 'n']], 'a')?->parts);
        self::assertSame(['n'], OptionBinding::qualified(['a' => 'n'], 'a')?->parts);
        self::assertNull(OptionBinding::qualified([], 'a'));
    }

    public function testClassifiedAcceptsEveryDeclaredName(): void
    {
        $this->expectNotToPerformAssertions();
        OptionBinding::classified(['a' => 'x', 'b' => true, 'c' => ['s', 'n']], ['a', 'b', 'c', 'd']);
        OptionBinding::classified([], []);
    }
}
