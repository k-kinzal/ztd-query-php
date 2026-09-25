<?php

declare(strict_types=1);

namespace Tests\Unit\Ears;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\LiteralMask;
use Requirements\Input\InvalidInputException;

#[CoversClass(LiteralMask::class)]
#[Small]
final class LiteralMaskTest extends TestCase
{
    #[DataProvider('providerApplyMasksLiterals')]
    public function testApplyMasksLiterals(string $text, string $expected): void
    {
        self::assertSame($expected, (new LiteralMask())->apply($text));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerApplyMasksLiterals(): array
    {
        return [
            'no literal' => ['The reader shall read.', 'The reader shall read.'],
            'empty' => ['', ''],
            'double quotes' => ['emit "shall, then" now', 'emit xxxxxxxxxxxxx now'],
            'single quotes' => ["emit 'a, b' now", 'emit xxxxxx now'],
            'backticks' => ['emit `shall` now', 'emit xxxxxxx now'],
            'two literals' => ['"a" and "b"', 'xxx and xxx'],
            'escaped quote' => ['"a\"b" c', 'xxxxxx c'],
            'escaped backslash' => ['"a\\\\" c', 'xxxxx c'],
            'other quote inside' => ['"it\'s" c', 'xxxxxx c'],
            'apostrophe after letter' => ["the user's input", "the user's input"],
            'apostrophe after digit' => ["the 90's", "the 90's"],
            'quote at start' => ["'abc' d", 'xxxxx d'],
            'quote after space' => ["a 'b' c", 'a xxx c'],
            'quote after punctuation' => ["(='b')", '(=xxx)'],
            'multibyte literal' => ['"é" x', 'xxxx x'],
        ];
    }

    #[DataProvider('providerApplyRejectsUnclosedLiterals')]
    public function testApplyRejectsUnclosedLiterals(string $text): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS: close quoted or code literals.');
        (new LiteralMask())->apply($text);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerApplyRejectsUnclosedLiterals(): array
    {
        return [
            'double quote' => ['The reader shall emit a "token.'],
            'backtick' => ['The reader shall emit `code.'],
            'single quote' => ["The reader shall emit 'token."],
            'escaped closing quote' => ['emit "a\"'],
            'trailing backslash' => ['emit "a\\'],
            'mismatched quotes' => ['emit "a\' now'],
        ];
    }
}
