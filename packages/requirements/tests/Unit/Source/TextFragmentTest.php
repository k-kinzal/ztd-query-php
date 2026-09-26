<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\InvalidInputException;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;

#[CoversClass(TextFragment::class)]
#[UsesClass(Unit::class)]
#[Small]
final class TextFragmentTest extends TestCase
{
    #[DataProvider('providerIsFragment')]
    public function testIsFragment(string $selector, bool $expected): void
    {
        self::assertSame($expected, TextFragment::isFragment($selector));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providerIsFragment(): array
    {
        return [
            'directive' => ['#:~:text=Names', true],
            'element fragment before directive' => ['#example:~:text=Names', true],
            'malformed directive' => ['#:~:', true],
            'css id' => ['#a', false],
            'css selector' => ['main p', false],
            'directive without hash' => [':~:text=Names', false],
            'hash later' => ['main #:~:text=Names', false],
        ];
    }

    public function testCreateDirectiveEncodingPreservesSyntaxCharactersAndUnicode(): void
    {
        $text = 'UTF-8, A&B + "日本語"';
        $fragment = TextFragment::create($text);

        self::assertSame('#:~:text=UTF%2D8%2C%20A%26B%20%2B%20%22%E6%97%A5%E6%9C%AC%E8%AA%9E%22', $fragment);
        self::assertSame($text, TextFragment::text($fragment));
        self::assertSame($text, TextFragment::text(str_replace('#:', '#example:', $fragment)));
    }

    public function testCreateNormalizesTheText(): void
    {
        self::assertSame('#:~:text=Names%20start.', TextFragment::create("  Names\n start. "));
    }

    #[DataProvider('providerTextDecodes')]
    public function testTextDecodesAndNormalizes(string $fragment, string $expected): void
    {
        self::assertSame($expected, TextFragment::text($fragment));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerTextDecodes(): array
    {
        return [
            'plain' => ['#:~:text=Names', 'Names'],
            'encoded hyphen' => ['#:~:text=a%2Db', 'a-b'],
            'lowercase escape' => ['#:~:text=a%2db', 'a-b'],
            'whitespace normalized' => ['#:~:text=%20Names%20%20start%0A', 'Names start'],
            'raw unicode' => ['#:~:text=日本語', '日本語'],
            'plus kept' => ['#:~:text=a+b', 'a+b'],
        ];
    }

    #[DataProvider('providerTextUnsupported')]
    public function testTextUnsupportedOrMalformedDirectivesFailExplicitly(string $fragment): void
    {
        $this->expectException(InvalidInputException::class);
        TextFragment::text($fragment);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerTextUnsupported(): array
    {
        return [
            ['#:~:text='],
            ['#:~:text=start,end'],
            ['#:~:text=prefix-,start'],
            ['#:~:text=start,-suffix'],
            ['#:~:text=first&text=second'],
            ['#:~:text=bad%xx'],
            ['#:~:text=%FF'],
            ['#:~:text=%20'],
            ['#text=missing-directive-marker'],
        ];
    }

    #[DataProvider('providerTextNotOneExactDirective')]
    public function testTextRejectsAnythingButOneExactDirective(string $fragment): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Use one exact Text Fragment (#:~:text=percent-encoded-text); ranges, context terms and multiple directives are unsupported.');
        TextFragment::text($fragment);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerTextNotOneExactDirective(): array
    {
        return [
            'empty text' => ['#:~:text='],
            'range' => ['#:~:text=start,end'],
            'prefix' => ['#:~:text=prefix-,start'],
            'several directives' => ['#:~:text=first&text=second'],
            'bad escape' => ['#:~:text=bad%xx'],
            'truncated escape' => ['#:~:text=bad%2'],
            'second hash' => ['#a#:~:text=Names'],
            'text before hash' => ['main#:~:text=Names'],
            'other directive' => ['#:~:note=Names'],
            'raw invalid UTF-8' => ["#:~:text=\xFF"],
        ];
    }

    #[DataProvider('providerTextEmptyOrInvalid')]
    public function testTextRejectsEmptyOrInvalidUtf8(string $fragment): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Text Fragment text must be nonempty UTF-8.');
        TextFragment::text($fragment);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerTextEmptyOrInvalid(): array
    {
        return [
            'invalid UTF-8' => ['#:~:text=%FF'],
            'blank' => ['#:~:text=%20'],
            'no-break space' => ['#:~:text=%C2%A0'],
        ];
    }
}
