<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf\Quoted;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Leaf\Quoted\Spelling;
use SqlSemantics\Platform\MySql\Rules\Strings;

#[CoversClass(Spelling::class)]
#[Small]
final class SpellingTest extends TestCase
{
    #[DataProvider('providerQuotedValues')]
    public function testReadRetainsChoicesSeparatelyFromDecodedBytes(string $token, bool $backslash): void
    {
        $value = (new Strings())->decode($token, $backslash);
        self::assertSame($token, (new Spelling())->read($token, $backslash)->spell($value));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerQuotedValues(): iterable
    {
        yield 'raw and escaped control' => ["'\x1a\\Z\n\\n\0\\0'", true];
        yield 'doubled single delimiter' => ["'a''b'", true];
        yield 'escaped single delimiter' => ["'a\\'b'", true];
        yield 'double delimiter' => ['"a""b"', true];
        yield 'all escapes' => ["'\\0\\b\\n\\r\\t\\Z\\\\\\'\\\"\\%\\_\\q'", true];
        yield 'escaped percent or literal backslash' => ["'\\%\\\\%'", true];
        yield 'no escapes' => ["'\\Z\\n\\%\\q'", false];
        yield 'multibyte offsets' => ["'猫\\Zé\x1a'", true];
    }
}
