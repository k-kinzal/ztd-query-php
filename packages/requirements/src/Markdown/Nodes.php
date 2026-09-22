<?php

declare(strict_types=1);

namespace Requirements\Markdown;

use InvalidArgumentException;
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

final class Nodes
{
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
                throw new InvalidArgumentException('Unsupported inline Markdown; use text, emphasis or code spans. Put links in reference or design fields.');
            }
            $result .= self::text($child, $literals);
        }
        return $result;
    }

    /** @return list<ListItem> */
    public static function items(Node $node): array
    {
        if (!$node instanceof ListBlock || $node->getListData()->type !== ListBlock::TYPE_BULLET) {
            throw new InvalidArgumentException('Expected a bullet list.');
        }
        $items = [];
        foreach ($node->children() as $item) {
            if (!$item instanceof ListItem) {
                throw new InvalidArgumentException('Expected a list item.');
            }
            $items[] = $item;
        }
        return $items;
    }

    public static function paragraph(Node $node): Paragraph
    {
        $child = $node->firstChild();
        if (!$child instanceof Paragraph || $child->next() !== null) {
            throw new InvalidArgumentException('Expected a single paragraph in this list item.');
        }
        return $child;
    }

    public static function link(Node $node): Link
    {
        $link = $node->firstChild();
        if (!$link instanceof Link || $link->next() !== null || $link->getUrl() === '') {
            throw new InvalidArgumentException('Expected one Markdown link with a nonempty destination.');
        }
        return $link;
    }

    public static function field(Node $node): ?string
    {
        $child = $node->firstChild();
        return $node instanceof Paragraph && $child instanceof Strong && $child->next() === null ? strtolower(self::text($child)) : null;
    }

    /** @return array{string, string} */
    public static function pair(Paragraph $node): array
    {
        $key = $node->firstChild();
        if (!$key instanceof Strong) {
            throw new InvalidArgumentException('Expected a bold field name followed by a colon.');
        }
        $label = self::text($key);
        $text = substr(self::text($node), strlen($label));
        if (str_ends_with($label, ':')) {
            return [substr($label, 0, -1), trim($text)];
        }
        if (!str_starts_with(ltrim($text), ':')) {
            throw new InvalidArgumentException('Expected a colon after the bold field name.');
        }
        return [$label, trim(substr(ltrim($text), 1))];
    }

    public static function escape(string $text): string
    {
        $text = preg_replace('/([\\\\`*_\[\]<>&!])/', '\\\\$1', $text) ?? $text;
        $text = preg_replace('/^(\s*)([#=+\-])/m', '$1\\\\$2', $text) ?? $text;
        return preg_replace('/^(\s*[0-9]+)([.)])/m', '$1\\\\$2', $text) ?? $text;
    }

    public static function destination(string $url): string
    {
        if (preg_match('/[<>()\\s]/', $url) !== 1) {
            return $url;
        }
        return '<' . str_replace(['<', '>', "\n", "\r", ' '], ['%3C', '%3E', '%0A', '%0D', '%20'], $url) . '>';
    }

    public static function anchor(string $id): string
    {
        return strtolower(str_replace('.', '', $id));
    }
}
