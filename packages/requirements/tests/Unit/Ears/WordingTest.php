<?php

declare(strict_types=1);

namespace Tests\Unit\Ears;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\Wording;

#[CoversClass(Wording::class)]
#[Small]
final class WordingTest extends TestCase
{
    #[DataProvider('providerHasContent')]
    public function testHasContent(string $text, bool $expected): void
    {
        self::assertSame($expected, Wording::hasContent($text));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providerHasContent(): array
    {
        return [
            'letters' => ['a token is read', true],
            'digits' => ['42', true],
            'letter among punctuation' => ['... x ...', true],
            'non-latin letter' => ['é', true],
            'non-latin digit' => ['٣', true],
            'empty' => ['', false],
            'space' => [" \t ", false],
            'punctuation' => ['...,;!', false],
        ];
    }
}
