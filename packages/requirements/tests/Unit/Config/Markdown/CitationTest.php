<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\LocalPath;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;

#[CoversClass(Citation::class)]
#[UsesClass(LocalPath::class)]
#[UsesClass(Source::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(Unit::class)]
#[Small]
final class CitationTest extends TestCase
{
    #[DataProvider('providerSelectors')]
    public function testSelectorDerivesTheSelectorFromTheLink(string $uri, string $format, string $file, string $url, ?string $selector, string $expected): void
    {
        $citation = new Citation(new Source('manual', $uri, $format, 'main p'), $file, '/project');
        self::assertSame($expected, $citation->selector($url, 'Names shall  start with a letter.', $selector));
    }

    /**
     * @return array<string, array{string, string, string, string, ?string, string}>
     */
    public static function providerSelectors(): array
    {
        return [
            'element ID' => ['source.html', 'html', '/project/definition.md', 'source.html#a', null, '#a'],
            'element ID from a subdirectory' => ['source.html', 'html', '/project/definitions/names.md', '../source.html#a', null, '#a'],
            'element ID with the source in a subdirectory' => ['docs/source.html', 'html', '/project/definition.md', 'docs/./source.html#a', null, '#a'],
            'source URI with a fragment' => ['source.html#main', 'html', '/project/definition.md', 'source.html#a', null, '#a'],
            'percent-encoded resource' => ['my source.html', 'html', '/project/definition.md', 'my%20source.html#a', null, '#a'],
            'percent-encoded element ID' => ['source.html', 'html', '/project/definition.md', 'source.html#%61', null, '#a'],
            'XML element ID' => ['rfc.xml', 'xml', '/project/definition.md', 'rfc.xml#rules', null, '#rules'],
            'IETF element ID' => ['rfc.xml', 'ietf', '/project/definition.md', 'rfc.xml#rules', null, '#rules'],
            'Markdown element ID' => ['rules.md', 'markdown', '/project/definition.md', 'rules.md#rules', null, '#rules'],
            'remote source' => ['https://example.org/spec.html', 'html', '/project/definition.md', 'https://example.org/spec.html#a', null, '#a'],
            'remote source with an uppercase scheme' => ['HTTPS://example.org/spec.html', 'html', '/project/definition.md', 'HTTPS://example.org/spec.html#a', null, '#a'],
            'Text Fragment' => ['source.html', 'html', '/project/definition.md', 'source.html#:~:text=Names%20shall%20start%20with%20a%20letter.', null, '#:~:text=Names%20shall%20start%20with%20a%20letter.'],
            'Text Fragment with a selector comment' => ['source.html', 'html', '/project/definition.md', 'source.html#:~:text=Names%20shall%20start%20with%20a%20letter.', 'main > p', 'main > p'],
            'selector comment agreeing with the anchor' => ['source.html', 'html', '/project/definition.md', 'source.html#a', '#a', '#a'],
            'selector comment without an anchor' => ['source.html', 'html', '/project/definition.md', 'source.html', '#a', '#a'],
            'CSS selector comment with an anchor' => ['source.html', 'html', '/project/definition.md', 'source.html#b', 'main p', 'main p'],
            'selector comment for a JSON source' => ['source.json', 'json', '/project/definition.md', 'source.json', '$.rules[0].text', '$.rules[0].text'],
        ];
    }

    #[DataProvider('providerRejectedCitations')]
    public function testSelectorRejectsCitationsThatDoNotIdentifyTheUnit(string $uri, string $format, string $url, ?string $selector, string $message): void
    {
        $citation = new Citation(new Source('manual', $uri, $format, 'main p'), '/project/definition.md', '/project');
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        $citation->selector($url, 'Names shall start with a letter.', $selector);
    }

    /**
     * @return array<string, array{string, string, string, ?string, string}>
     */
    public static function providerRejectedCitations(): array
    {
        return [
            'other resource' => ['source.html', 'html', 'different.html#a', null, 'An evidence citation must link to this definition\'s source resource.'],
            'fragment only' => ['source.html', 'html', '#a', null, 'An evidence citation must link to this definition\'s source resource.'],
            'resource in another directory' => ['source.html', 'html', '../source.html#a', null, 'An evidence citation must link to this definition\'s source resource.'],
            'other remote resource' => ['https://example.org/spec.html', 'html', 'https://example.org/other.html#a', null, 'An evidence citation must link to this definition\'s source resource.'],
            'local link to a remote source' => ['https://example.org/spec.html', 'html', 'spec.html#a', null, 'An evidence citation must link to this definition\'s source resource.'],
            'wrong Text Fragment text' => ['source.html', 'html', 'source.html#:~:text=Different%20text.', null, 'An exact Text Fragment citation must identify the complete quoted HTML unit.'],
            'Text Fragment outside HTML' => ['rules.md', 'markdown', 'rules.md#:~:text=Names%20shall%20start%20with%20a%20letter.', null, 'An exact Text Fragment citation must identify the complete quoted HTML unit.'],
            'Text Fragment range' => ['source.html', 'html', 'source.html#:~:text=Names,letter.', null, 'Use one exact Text Fragment'],
            'anchor disagreeing with the selector comment' => ['source.html', 'html', 'source.html#b', '#a', 'The citation anchor disagrees with the evidence selector.'],
            'no anchor' => ['source.html', 'html', 'source.html', null, 'Supply a selector comment, an element-ID citation, or an exact Text Fragment citation.'],
            'CSS anchor' => ['source.html', 'html', 'source.html#main%20p', null, 'Supply a selector comment, an element-ID citation, or an exact Text Fragment citation.'],
            'element ID for a JSON source' => ['source.json', 'json', 'source.json#a', null, 'Supply a selector comment, an element-ID citation, or an exact Text Fragment citation.'],
            'element ID for a text source' => ['source.txt', 'text', 'source.txt#a', null, 'Supply a selector comment, an element-ID citation, or an exact Text Fragment citation.'],
        ];
    }

    #[DataProvider('providerValidSelectors')]
    public function testValidateAcceptsSelectorsThatIdentifyTheQuote(string $format, string $selector): void
    {
        (new Citation(new Source('manual', 'source', $format, 'main p'), '/project/definition.md', '/project'))->validate($selector, "Names shall start\nwith a letter.");
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerValidSelectors(): array
    {
        return [
            'element ID' => ['html', '#a'],
            'CSS selector' => ['html', 'main p'],
            'element ID outside HTML' => ['markdown', '#a'],
            'exact Text Fragment' => ['html', '#:~:text=Names%20shall%20start%20with%20a%20letter.'],
        ];
    }

    #[DataProvider('providerInvalidSelectors')]
    public function testValidateRejectsATextFragmentThatSelectsOtherText(string $format, string $selector): void
    {
        $citation = new Citation(new Source('manual', 'source', $format, 'main p'), '/project/definition.md', '/project');
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('An exact Text Fragment selector must identify the complete quoted HTML unit.');
        $citation->validate($selector, 'Names shall start with a letter.');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerInvalidSelectors(): array
    {
        return [
            'other text' => ['html', '#:~:text=Different%20text.'],
            'part of the text' => ['html', '#:~:text=Names%20shall%20start'],
            'outside HTML' => ['markdown', '#:~:text=Names%20shall%20start%20with%20a%20letter.'],
        ];
    }

    #[DataProvider('providerUrls')]
    public function testUrlCreatesTheCitationLink(string $uri, string $format, string $file, string $selector, string $expected): void
    {
        $citation = new Citation(new Source('manual', $uri, $format, 'main p'), $file, '/project');
        self::assertSame($expected, $citation->url($selector, "Names shall start\nwith a letter."));
    }

    /**
     * @return array<string, array{string, string, string, string, string}>
     */
    public static function providerUrls(): array
    {
        return [
            'element ID' => ['source.html', 'html', '/project/definition.md', '#a', 'source.html#a'],
            'element ID from a subdirectory' => ['source.html', 'html', '/project/definitions/names.md', '#a', '../source.html#a'],
            'element ID from a nested subdirectory' => ['source.html', 'html', '/project/a/b/names.md', '#a', '../../source.html#a'],
            'source in a subdirectory' => ['docs/source.html', 'html', '/project/definition.md', '#a', 'docs/source.html#a'],
            'source in a sibling directory' => ['docs/source.html', 'html', '/project/definitions/names.md', '#a', '../docs/source.html#a'],
            'source URI with a fragment' => ['source.html#main', 'html', '/project/definition.md', '#a', 'source.html#a'],
            'source name with a space' => ['my source.html', 'html', '/project/definition.md', '#a', 'my%20source.html#a'],
            'remote source' => ['https://example.org/spec.html#main', 'html', '/project/definition.md', '#a', 'https://example.org/spec.html#a'],
            'Text Fragment' => ['source.html', 'html', '/project/definition.md', '#:~:text=Names', 'source.html#:~:text=Names'],
            'CSS selector' => ['source.html', 'html', '/project/definition.md', 'main > p:first-child', 'source.html#:~:text=Names%20shall%20start%20with%20a%20letter.'],
            'XML element ID' => ['rfc.xml', 'xml', '/project/definition.md', '#rules', 'rfc.xml#rules'],
            'IETF element ID' => ['rfc.xml', 'ietf', '/project/definition.md', '#rules', 'rfc.xml#rules'],
            'Markdown element ID' => ['rules.md', 'markdown', '/project/definition.md', '#rules', 'rules.md#rules'],
            'Markdown heading selector' => ['rules.md', 'markdown', '/project/definition.md', 'h1', 'rules.md'],
            'JSON element ID' => ['source.json', 'json', '/project/definition.md', '#a', 'source.json'],
            'Text Fragment outside HTML' => ['rules.md', 'markdown', '/project/definition.md', '#:~:text=Names', 'rules.md'],
        ];
    }

    #[DataProvider('providerIds')]
    public function testIsIdTellsWhetherASelectorIsOneElementId(string $selector, bool $expected): void
    {
        self::assertSame($expected, Citation::isId($selector));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providerIds(): array
    {
        return [
            'letter' => ['#a', true],
            'hyphen, underscore and digits' => ['#_a-b_1', true],
            'no hash' => ['a', false],
            'leading digit' => ['#1a', false],
            'space' => ['#a b', false],
            'two IDs' => ['#a#b', false],
            'trailing line break' => ["#a\n", false],
            'leading text' => ['p#a', false],
            'hash only' => ['#', false],
        ];
    }
}
