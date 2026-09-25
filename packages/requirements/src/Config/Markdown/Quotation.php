<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use Requirements\Input\InvalidInputException;
use stdClass;

final class Quotation
{
    /** @var array{url: string, label: string}|null */
    public ?array $link = null;

    public function read(BlockQuote $node, ?Citation $citation, ?Link $attribution = null): stdClass
    {
        if ($citation === null) {
            throw new InvalidInputException('A quotation needs a declared source.');
        }
        $this->link = $attribution === null ? null : ['url' => $attribution->getUrl(), 'label' => Nodes::text($attribution)];
        $selector = null;
        $paragraphs = [];
        foreach ($node->children() as $child) {
            if ($child instanceof HtmlBlock && $child === $node->firstChild()) {
                $selector = self::annotation($child);
            } elseif ($child instanceof Paragraph && $child->firstChild() instanceof Link && $child->firstChild()->next() === null && $child->next() === null) {
                if ($this->link !== null) {
                    throw new InvalidInputException('An evidence quotation can have only one source citation.');
                }
                $link = Nodes::link($child);
                $this->link = ['url' => $link->getUrl(), 'label' => Nodes::text($link)];
            } elseif ($child instanceof Paragraph) {
                $paragraphs[] = Nodes::text($child);
            } else {
                throw new InvalidInputException('A quotation contains a selector comment, quoted prose, then an optional source link.');
            }
        }
        $quote = implode("\n\n", $paragraphs);
        if (trim($quote) === '') {
            throw new InvalidInputException('Evidence needs quoted source text.');
        }
        if ($this->link !== null) {
            $selector = $citation->selector($this->link['url'], $quote, $selector);
        }
        if ($selector === null || trim($selector) === '') {
            throw new InvalidInputException('Evidence needs a selector comment or a source link identifying the quoted unit.');
        }
        $citation->validate($selector, $quote);
        return (object) ['selector' => $selector, 'quote' => $quote];
    }

    public static function annotation(HtmlBlock $node): string
    {
        if ($node->getType() !== HtmlBlock::TYPE_2_COMMENT || preg_match('/\A<!--\s*(?:\*\*selector:\*\*|selector:)\s*([^\r\n]+?)\s*-->\s*\z/', $node->getLiteral(), $parts) !== 1 || str_contains($parts[1], '-->')) {
            throw new InvalidInputException('Only a selector comment is allowed at the start of an evidence quotation.');
        }
        $selector = html_entity_decode($parts[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return str_starts_with($selector, '\\#') ? substr($selector, 1) : $selector;
    }

    /** @param array{url: string, label: string}|null $link */
    public static function render(string $selector, string $quote, ?Citation $citation, ?array $link): string
    {
        $url = $link['url'] ?? $citation?->url($selector, $quote);
        $annotation = true;
        if ($citation !== null && $url !== null) {
            try {
                $annotation = $citation->selector($url, $quote, null) !== $selector;
            } catch (InvalidInputException) {
                $annotation = true;
            }
        }
        $text = $annotation ? '> <!-- selector: ' . htmlspecialchars($selector, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n" : '';
        $text .= '> ' . str_replace("\n", "\n> ", Nodes::escape($quote));
        if ($url !== null) {
            $text .= "\n\n[" . Nodes::escape($link['label'] ?? 'Source') . '](' . Nodes::destination($url) . ')';
        }
        return $text;
    }
}
