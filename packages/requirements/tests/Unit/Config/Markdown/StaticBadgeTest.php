<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\StaticBadge;
use Requirements\Input\InvalidInputException;

#[CoversClass(StaticBadge::class)]
#[Small]
final class StaticBadgeTest extends TestCase
{
    #[DataProvider('providerAgreeingBadges')]
    public function testValidateAcceptsAgreeingOrUncheckedImages(string $url, string $field, string $value): void
    {
        StaticBadge::validate($url, $field, $value);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function providerAgreeingBadges(): array
    {
        return [
            'other host' => ['https://example.org/badge/status-supported-blue', 'status', 'unsupported'],
            'relative image' => ['assets/grammar.svg', 'label', 'grammar'],
            'other shields path' => ['https://img.shields.io/static/v1?label=status&message=supported', 'status', 'unsupported'],
            'other shields role' => ['https://img.shields.io/badge/color-red-blue', 'label', 'anything'],
            'status' => ['https://img.shields.io/badge/status-unsupported-orange', 'status', 'unsupported'],
            'kind' => ['https://img.shields.io/badge/kind-requirement-blue', 'kind', 'requirement'],
            'origin' => ['https://img.shields.io/badge/origin-original-blue', 'origin', 'original'],
            'category' => ['https://img.shields.io/badge/category-lexical-blue', 'category', 'lexical'],
            'escaped leading dash' => ['https://img.shields.io/badge/category---names-blue', 'category', '-names'],
            'escaped trailing dash' => ['https://img.shields.io/badge/label-end---blue', 'label', 'end-'],
            'escaped underscore' => ['https://img.shields.io/badge/label-under__score-blue', 'label', 'under_score'],
            'underscore as space' => ['https://img.shields.io/badge/label-with_spaces-blue', 'label', 'with spaces'],
            'percent-encoded space' => ['https://img.shields.io/badge/label-with%20spaces-blue', 'label', 'with spaces'],
            'unicode' => ['https://img.shields.io/badge/label-%E6%97%A5%E6%9C%AC%E8%AA%9E-blue', 'label', '日本語'],
        ];
    }

    #[DataProvider('providerMisleadingBadges')]
    public function testValidateRejectsMisleadingStaticImages(string $url, string $field, string $value): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Static badge image text and role must agree with its alt text and title.');
        StaticBadge::validate($url, $field, $value);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function providerMisleadingBadges(): array
    {
        return [
            'other value' => ['https://img.shields.io/badge/status-supported-blue', 'status', 'unsupported'],
            'other role' => ['https://img.shields.io/badge/status-supported-blue', 'label', 'supported'],
            'unescaped dash' => ['https://img.shields.io/badge/label-a-b-blue', 'label', 'a-b'],
            'no color' => ['https://img.shields.io/badge/label-grammar', 'label', 'grammar'],
            'underscore is not kept' => ['https://img.shields.io/badge/label-under_score-blue', 'label', 'under_score'],
        ];
    }
}
