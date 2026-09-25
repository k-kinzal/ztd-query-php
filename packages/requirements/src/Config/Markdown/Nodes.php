<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use Requirements\Input\InvalidInputException;

/**
 * Reads the CommonMark nodes that cards are built from, and escapes text written back.
 */
final class Nodes
{
    /**
     * Returns the text of a node.
     *
     * @param Node $node The node
     * @param bool $literals Whether code spans keep their backticks
     *
     * @return string The text of the node and its inline children
     *
     * @throws InvalidInputException When the node holds inline Markdown other than text, emphasis and code
     */
    public static function text(Node $node, bool $literals = false): string
    {
        if ($node instanceof Text) {
            return $node->getLiteral();
        }
        if ($node instanceof Code) {
            return $literals ? '`' . $node->getLiteral() . '`' : $node->getLiteral();
        }
        if ($node instanceof Newline) {
            return "\n";
        }
        $result = '';
        foreach ($node->children() as $child) {
            if (!$child instanceof Text && !$child instanceof Code && !$child instanceof Newline && !$child instanceof Strong && !$child instanceof Emphasis) {
                throw new InvalidInputException('Unsupported inline Markdown; use text, emphasis or code spans. Put links in reference or design fields.');
            }
            $result .= self::text($child, $literals);
        }
        return $result;
    }

    /**
     * Returns the items of a bullet list.
     *
     * @param Node $node The list
     *
     * @return list<ListItem> The items
     *
     * @throws InvalidInputException When the node is not a bullet list
     */
    public static function items(Node $node): array
    {
        if (!$node instanceof ListBlock || $node->getListData()->type !== ListBlock::TYPE_BULLET) {
            throw new InvalidInputException('Expected a bullet list.');
        }
        $items = [];
        foreach ($node->children() as $item) {
            if (!$item instanceof ListItem) {
                throw new InvalidInputException('Expected a list item.');
            }
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Returns the single paragraph of a list item.
     *
     * @param Node $node The list item
     *
     * @return Paragraph The paragraph
     *
     * @throws InvalidInputException When the item holds anything else
     */
    public static function paragraph(Node $node): Paragraph
    {
        $child = $node->firstChild();
        if (!$child instanceof Paragraph || $child->next() !== null) {
            throw new InvalidInputException('Expected a single paragraph in this list item.');
        }
        return $child;
    }

    /**
     * Returns the single link of a paragraph.
     *
     * @param Node $node The paragraph
     *
     * @return Link The link
     *
     * @throws InvalidInputException When the paragraph holds anything else or the destination is empty
     */
    public static function link(Node $node): Link
    {
        $link = $node->firstChild();
        if (!$link instanceof Link || $link->next() !== null || $link->getUrl() === '') {
            throw new InvalidInputException('Expected one Markdown link with a nonempty destination.');
        }
        return $link;
    }

    /**
     * Returns the name of a bold field heading.
     *
     * @param Node $node The block
     *
     * @return string|null The lowercase field name, or null when the block is not a field heading
     *
     * @throws InvalidInputException When the bold text holds unsupported inline Markdown
     */
    public static function field(Node $node): ?string
    {
        $child = $node->firstChild();
        return $node instanceof Paragraph && $child instanceof Strong && $child->next() === null ? strtolower(self::text($child)) : null;
    }

    /**
     * Splits a "**name:** value" paragraph.
     *
     * @param Paragraph $node The paragraph
     *
     * @return array{string, string} The name and the trimmed value
     *
     * @throws InvalidInputException When the paragraph does not start with a bold name and a colon
     */
    public static function pair(Paragraph $node): array
    {
        $key = $node->firstChild();
        if (!$key instanceof Strong) {
            throw new InvalidInputException('Expected a bold field name followed by a colon.');
        }
        $label = self::text($key);
        $text = substr(self::text($node), strlen($label));
        if (str_ends_with($label, ':')) {
            return [substr($label, 0, -1), trim($text)];
        }
        if (!str_starts_with(ltrim($text), ':')) {
            throw new InvalidInputException('Expected a colon after the bold field name.');
        }
        return [$label, trim(substr(ltrim($text), 1))];
    }

    /**
     * Escapes text so Markdown reads it back unchanged.
     *
     * @param string $text The text
     *
     * @return string The escaped text
     */
    public static function escape(string $text): string
    {
        $text = preg_replace('/([\\\\`*_\[\]<>&!])/', '\\\\$1', $text) ?? $text;
        $text = preg_replace('/^(\s*)([#=+\-])/m', '$1\\\\$2', $text) ?? $text;
        return preg_replace('/^(\s*[0-9]+)([.)])/m', '$1\\\\$2', $text) ?? $text;
    }

    /**
     * Writes a link destination, in angle brackets when it contains spaces or brackets.
     *
     * @param string $url The destination
     *
     * @return string The Markdown destination
     */
    public static function destination(string $url): string
    {
        if (preg_match('/[<>()\\s]/', $url) !== 1) {
            return $url;
        }
        return '<' . str_replace(['<', '>', "\n", "\r", ' '], ['%3C', '%3E', '%0A', '%0D', '%20'], $url) . '>';
    }

    /**
     * Returns the heading anchor of an item ID.
     *
     * @param string $id The item ID
     *
     * @return string The lowercase ID without dots
     */
    public static function anchor(string $id): string
    {
        return strtolower(str_replace('.', '', $id));
    }
}
