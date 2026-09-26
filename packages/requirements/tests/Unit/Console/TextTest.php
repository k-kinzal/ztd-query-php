<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Console\Text;
use stdClass;

#[CoversClass(Text::class)]
#[Small]
final class TextTest extends TestCase
{
    #[DataProvider('providerPlainValues')]
    public function testPlainConvertsScalarValues(mixed $value, string $expected): void
    {
        self::assertSame($expected, Text::plain($value));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerPlainValues(): array
    {
        return [
            'string' => ['Names shall start with a letter.', 'Names shall start with a letter.'],
            'integer' => [42, '42'],
            'float' => [1.5, '1.5'],
            'empty string' => ['', ''],
            'null' => [null, ''],
            'boolean' => [true, ''],
            'list' => [['text'], ''],
            'object' => [new stdClass(), ''],
        ];
    }

    #[DataProvider('providerPlainControlCharacters')]
    public function testPlainRemovesControlCharacters(string $value, string $expected): void
    {
        self::assertSame($expected, Text::plain($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerPlainControlCharacters(): array
    {
        return [
            'null byte' => ["a\x00b", 'ab'],
            'bell' => ["a\x07b", 'ab'],
            'backspace' => ["a\x08b", 'ab'],
            'vertical tab' => ["a\x0bb", 'ab'],
            'form feed' => ["a\x0cb", 'ab'],
            'shift out' => ["a\x0eb", 'ab'],
            'escape sequence' => ["a\x1b[31mb", 'a[31mb'],
            'unit separator' => ["a\x1fb", 'ab'],
            'delete' => ["a\x7fb", 'ab'],
            'several' => ["\x01a\x02b\x03", 'ab'],
            'tab kept' => ["a\tb", "a\tb"],
            'line feed kept' => ["a\nb", "a\nb"],
            'carriage return kept' => ["a\rb", "a\rb"],
            'space kept' => ['a b', 'a b'],
            'multibyte kept' => ['Grüße · 名前', 'Grüße · 名前'],
        ];
    }

    #[DataProvider('providerPlainMarkup')]
    public function testPlainEscapesConsoleMarkup(string $value, string $expected): void
    {
        self::assertSame($expected, Text::plain($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerPlainMarkup(): array
    {
        return [
            'style tags' => ['<info>x</info>', '\\<info\\>x\\</info\\>'],
            'error tag' => ['<error>', '\\<error\\>'],
            'backslash' => ['a\\b', 'a\\b'],
        ];
    }
}
