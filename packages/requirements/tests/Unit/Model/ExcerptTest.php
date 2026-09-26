<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Excerpt;

#[CoversClass(Excerpt::class)]
#[UsesClass(Fields::class)]
#[Small]
final class ExcerptTest extends TestCase
{
    public function testFromReadsSelectorAndQuote(): void
    {
        $excerpt = Excerpt::from(['selector' => '#a', 'quote' => 'Names shall start with a letter.']);
        self::assertSame('#a', $excerpt->selector);
        self::assertSame('Names shall start with a letter.', $excerpt->quote);
    }

    #[DataProvider('providerFromRejectsInvalidEntries')]
    public function testFromRejectsInvalidEntries(mixed $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        Excerpt::from($value);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerFromRejectsInvalidEntries(): array
    {
        return [
            'not a mapping' => ['#a', 'evidence must be a mapping.'],
            'unknown field' => [['selector' => '#a', 'quote' => 'q', 'note' => 'n'], "evidence: unknown field 'note'."],
            'missing selector' => [['quote' => 'q'], 'selector must be a nonempty string.'],
            'blank quote' => [['selector' => '#a', 'quote' => ' '], 'quote must be a nonempty string.'],
        ];
    }
}
