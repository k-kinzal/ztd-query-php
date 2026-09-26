<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\StaticBadge;
use Requirements\Input\InvalidInputException;
use stdClass;
use Tests\Fake\MarkdownNodes;

#[CoversClass(Badges::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(StaticBadge::class)]
#[Small]
final class BadgesTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerBlocks')]
    public function testIsParagraphTellsWhetherABlockIsABadgeRow(string $markdown, bool $expected): void
    {
        self::assertSame($expected, Badges::isParagraph(MarkdownNodes::first($markdown)));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providerBlocks(): array
    {
        return [
            'badge row' => ['![grammar](grammar.svg) ![lexical](lexical.svg)', true],
            'text before the image' => ['see ![grammar](grammar.svg)', false],
            'statement' => ['The reader shall emit a tree.', false],
            'heading with an image' => ['# ![grammar](grammar.svg)', false],
            'quotation with an image' => ['> ![grammar](grammar.svg)', false],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadSetsTheFieldsAndLabelsOfARow(): void
    {
        $item = new stdClass();
        $badges = new Badges();
        $badges->read(MarkdownNodes::first("![requirement](https://img.shields.io/badge/kind-requirement-blue) ![grammar](assets/grammar.svg)\n![lexical](../assets/lexical.svg \"category\")\n![strictness](https://img.shields.io/badge/label-strictness-blue)"), $item);
        self::assertEquals((object) ['kind' => 'requirement', 'labels' => ['grammar', 'strictness'], 'category' => 'lexical'], $item);
        self::assertSame([
            'kind' => ['requirement' => ['url' => 'https://img.shields.io/badge/kind-requirement-blue', 'title' => null]],
            'label' => ['grammar' => ['url' => 'assets/grammar.svg', 'title' => null], 'strictness' => ['url' => 'https://img.shields.io/badge/label-strictness-blue', 'title' => null]],
            'category' => ['lexical' => ['url' => '../assets/lexical.svg', 'title' => 'category']],
        ], $badges->images);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadAppendsToTheLabelsAlreadyRead(): void
    {
        $item = (object) ['labels' => ['grammar']];
        (new Badges())->read(MarkdownNodes::first('![strictness](strictness.svg)'), $item);
        self::assertSame(['grammar', 'strictness'], $item->labels);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadReadsTheTextOfEmphasisInTheAltText(): void
    {
        $item = new stdClass();
        (new Badges())->read(MarkdownNodes::first('![a *b*](b.svg "status")'), $item);
        self::assertSame('a b', $item->status);
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedRows')]
    public function testReadRejectsMalformedRows(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Badges())->read(MarkdownNodes::first($markdown), new stdClass());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedRows(): array
    {
        return [
            'text after the image' => ['![grammar](grammar.svg) text', 'The badge row must contain only images with nonempty destinations.'],
            'empty destination' => ['![grammar]()', 'The badge row must contain only images with nonempty destinations.'],
            'emphasis after the image' => ['![grammar](grammar.svg) *a*', 'The badge row must contain only images with nonempty destinations.'],
            'empty alt text' => ['![](grammar.svg)', 'Badge alt text must contain its attribute value.'],
            'blank alt text' => ['![ ](grammar.svg)', 'Badge alt text must contain its attribute value.'],
            'duplicate status' => ['![supported](a.svg "status") ![unsupported](b.svg "status")', "Duplicate 'status' badge or field."],
            'duplicate label' => ['![parser](a.svg) ![parser](b.svg)', 'Label badges must be unique.'],
            'misleading static badge' => ['![unsupported](https://img.shields.io/badge/status-supported-blue)', 'Static badge image text and role must agree with its alt text and title.'],
            'unknown badge role' => ['![value](a.svg "lable")', 'A custom badge title must be kind, status, origin, category or label.'],
            'inline HTML in the alt text' => ['![<b>a</b>](a.svg)', 'Unsupported inline Markdown'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRejectsAFieldTheItemAlreadyHas(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("Duplicate 'category' badge or field.");
        (new Badges())->read(MarkdownNodes::first('![grammar](a.svg "category")'), (object) ['category' => 'lexical']);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRejectsLabelsThatAreNotAList(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Label badges must be unique.');
        (new Badges())->read(MarkdownNodes::first('![grammar](a.svg)'), (object) ['labels' => 'parser']);
    }

    #[DataProvider('providerFields')]
    public function testFieldDecidesTheFieldABadgeSets(string $url, ?string $title, string $expected): void
    {
        self::assertSame($expected, Badges::field($url, $title));
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function providerFields(): array
    {
        return [
            'title kind' => ['a.svg', 'kind', 'kind'],
            'title status' => ['a.svg', 'status', 'status'],
            'title origin' => ['a.svg', 'origin', 'origin'],
            'title category' => ['a.svg', 'category', 'category'],
            'title label' => ['https://img.shields.io/badge/status-supported-blue', 'label', 'label'],
            'static status' => ['https://img.shields.io/badge/status-supported-blue', null, 'status'],
            'static kind' => ['https://img.shields.io/badge/kind-requirement-blue', null, 'kind'],
            'static origin' => ['https://img.shields.io/badge/origin-original-blue', null, 'origin'],
            'static category' => ['https://img.shields.io/badge/category-lexical-blue', null, 'category'],
            'static label' => ['https://img.shields.io/badge/label-grammar-blue', null, 'label'],
            'static other role' => ['https://img.shields.io/badge/color-red-blue', null, 'label'],
            'static role without dash' => ['https://img.shields.io/badge/status', null, 'label'],
            'role later in the path' => ['https://img.shields.io/x/badge/status-supported-blue', null, 'label'],
            'other host' => ['https://example.org/badge/status-supported-blue', null, 'label'],
            'local image' => ['assets/grammar.svg', null, 'label'],
        ];
    }

    public function testFieldRejectsATitleNamingAnotherField(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('A custom badge title must be kind, status, origin, category or label.');
        Badges::field('a.svg', 'lable');
    }

    /**
     * @param array{url: string, title: ?string}|null $image
     */
    #[DataProvider('providerRenderedBadges')]
    public function testRenderWritesAMarkdownImage(string $field, string $value, ?array $image, string $expected): void
    {
        self::assertSame($expected, Badges::render($field, $value, $image));
    }

    /**
     * @return array<string, array{string, string, array{url: string, title: ?string}|null, string}>
     */
    public static function providerRenderedBadges(): array
    {
        return [
            'new kind' => ['kind', 'requirement', null, '![requirement](https://img.shields.io/badge/kind-requirement-blue)'],
            'new unsupported status' => ['status', 'unsupported', null, '![unsupported](https://img.shields.io/badge/status-unsupported-orange)'],
            'new supported status' => ['status', 'supported', null, '![supported](https://img.shields.io/badge/status-supported-blue)'],
            'new unsupported label' => ['label', 'unsupported', null, '![unsupported](https://img.shields.io/badge/label-unsupported-blue)'],
            'leading dash' => ['category', '-names', null, '![\\-names](https://img.shields.io/badge/category---names-blue)'],
            'trailing dash' => ['label', 'end-', null, '![end-](https://img.shields.io/badge/label-end---blue)'],
            'underscore' => ['label', 'under_score', null, '![under\\_score](https://img.shields.io/badge/label-under__score-blue)'],
            'spaces' => ['label', 'with spaces', null, '![with spaces](https://img.shields.io/badge/label-with%20spaces-blue)'],
            'unicode' => ['label', '日本語', null, '![日本語](https://img.shields.io/badge/label-%E6%97%A5%E6%9C%AC%E8%AA%9E-blue)'],
            'read image' => ['label', 'grammar', ['url' => 'assets/grammar.svg', 'title' => null], '![grammar](assets/grammar.svg)'],
            'read image with title' => ['category', 'lexical', ['url' => '../assets/lexical.svg', 'title' => 'category'], '![lexical](../assets/lexical.svg "category")'],
            'read image with quote in title' => ['category', 'lexical', ['url' => 'a.svg', 'title' => 'a"b'], '![lexical](a.svg "a&quot;b")'],
            'read image with space' => ['label', 'grammar', ['url' => 'my assets/grammar.svg', 'title' => null], '![grammar](<my%20assets/grammar.svg>)'],
        ];
    }
}
