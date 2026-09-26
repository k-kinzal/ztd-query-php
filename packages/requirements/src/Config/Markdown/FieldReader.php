<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Reads the value of one bold field section of a card.
 *
 * Prose fields are paragraphs; list fields are bullet lists, or "None." for an empty list;
 * labels may also be a paragraph of badge images. Link targets and label badge images are
 * kept so that writing the card back reproduces them.
 */
final class FieldReader
{
    /**
     * @var array<string, string> Link targets of the requirements or related field by linked ID
     */
    public array $links = [];

    /**
     * @var array<string, string> Badge image URLs of the labels field by label
     */
    public array $badges = [];

    /**
     * Reads a field value.
     *
     * @param string $name The field name
     * @param list<Node> $nodes The blocks of the section
     *
     * @return mixed The value in the shape of the YAML field
     *
     * @throws InvalidInputException When the section is empty, has the wrong blocks or names an unknown field
     */
    public function read(string $name, array $nodes): mixed
    {
        if ($nodes === []) {
            throw new InvalidInputException("Markdown field '$name' needs a value.");
        }
        if (in_array($name, ['kind', 'status', 'origin', 'category', 'reason'], true)) {
            return $this->prose($nodes);
        }
        if (count($nodes) === 1 && $nodes[0] instanceof Paragraph && $nodes[0]->firstChild() instanceof Text && $nodes[0]->firstChild()->next() === null && $nodes[0]->firstChild()->getLiteral() === 'None.' && in_array($name, ['evidence', 'tests', 'requirements', 'related', 'labels', 'design'], true)) {
            return [];
        }
        if (count($nodes) !== 1) {
            throw new InvalidInputException("Markdown field '$name' needs one list or badge paragraph.");
        }
        $node = $nodes[0];
        return match ($name) {
            'evidence' => $this->evidence($node),
            'tests' => $this->tests($node),
            'requirements', 'related' => $this->references($node),
            'labels' => $this->labels($node),
            'design' => $this->design($node),
            'metadata' => (new MetadataReader())->read($node),
            default => throw new InvalidInputException("Unknown Markdown field '$name'."),
        };
    }

    /**
     * Reads prose paragraphs, separated by blank lines.
     *
     * @param list<Node> $nodes The blocks
     *
     * @return string The text
     *
     * @throws InvalidInputException When a block is not a paragraph or has unsupported inline Markdown
     */
    public function prose(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            if (!$node instanceof Paragraph) {
                throw new InvalidInputException('Expected prose paragraphs.');
            }
            $parts[] = Nodes::text($node);
        }
        return implode("\n\n", $parts);
    }

    /**
     * Reads an evidence list of bold selector fields, each followed by one block quotation.
     *
     * @param Node $node The list
     *
     * @return list<stdClass> The evidence entries
     *
     * @throws InvalidInputException When an entry lacks its selector or quotation
     */
    public function evidence(Node $node): array
    {
        $result = [];
        foreach (Nodes::items($node) as $item) {
            $selector = $item->firstChild();
            if (!$selector instanceof Paragraph) {
                throw new InvalidInputException('Evidence needs a selector and a block quotation.');
            }
            [$name, $value] = Nodes::pair($selector);
            $quote = $selector->next();
            if ($name !== 'selector' || !$quote instanceof BlockQuote || $quote->next() !== null) {
                throw new InvalidInputException('Evidence must use a bold selector field followed by one block quotation.');
            }
            $paragraphs = [];
            foreach ($quote->children() as $paragraph) {
                $paragraphs[] = $paragraph;
            }
            $result[] = (object) ['selector' => $value, 'quote' => $this->prose($paragraphs)];
        }
        return $result;
    }

    /**
     * Reads a tests list of "**runner:** target" entries.
     *
     * @param Node $node The list
     *
     * @return list<stdClass> The test references
     *
     * @throws InvalidInputException When an entry is not a bold runner and a target
     */
    public function tests(Node $node): array
    {
        $result = [];
        foreach (Nodes::items($node) as $item) {
            [$runner, $target] = Nodes::pair(Nodes::paragraph($item));
            $result[] = (object) ['runner' => $runner, 'target' => $target];
        }
        return $result;
    }

    /**
     * Reads a list of links to other items and remembers their targets.
     *
     * @param Node $node The list
     *
     * @return list<string> The linked item IDs
     *
     * @throws InvalidInputException When an entry is not a single link
     */
    public function references(Node $node): array
    {
        $result = [];
        foreach (Nodes::items($node) as $item) {
            $link = Nodes::link(Nodes::paragraph($item));
            $id = Nodes::text($link);
            $this->links[$id] = $link->getUrl();
            $result[] = $id;
        }
        return $result;
    }

    /**
     * Reads labels from a bullet list or a paragraph of badge images.
     *
     * @param Node $node The list or paragraph
     *
     * @return list<string> The labels
     *
     * @throws InvalidInputException When the paragraph holds anything but badge images or no labels
     */
    public function labels(Node $node): array
    {
        if ($node instanceof ListBlock) {
            return array_map(static fn (Node $item): string => Nodes::text(Nodes::paragraph($item)), Nodes::items($node));
        }
        if (!$node instanceof Paragraph) {
            throw new InvalidInputException('Labels must be a bullet list or a paragraph of badge images.');
        }
        $result = [];
        foreach ($node->children() as $image) {
            if ($image instanceof Newline || ($image instanceof Text && trim($image->getLiteral()) === '')) {
                continue;
            }
            if (!$image instanceof Image || $image->getUrl() === '') {
                throw new InvalidInputException('Use ![label](image-url) for each label badge.');
            }
            $label = Nodes::text($image);
            $this->badges[$label] = $image->getUrl();
            $result[] = $label;
        }
        if ($result === []) {
            throw new InvalidInputException('The badge paragraph must contain labels.');
        }
        return $result;
    }

    /**
     * Reads design references: links with optional text, or plain text.
     *
     * @param Node $node The list
     *
     * @return list<stdClass> The design references
     *
     * @throws InvalidInputException When an entry is not a single paragraph
     */
    public function design(Node $node): array
    {
        $result = [];
        foreach (Nodes::items($node) as $item) {
            $paragraph = Nodes::paragraph($item);
            try {
                $link = Nodes::link($paragraph);
            } catch (InvalidInputException) {
                $result[] = (object) ['text' => Nodes::text($paragraph)];
                continue;
            }
            $entry = (object) ['url' => $link->getUrl()];
            $text = Nodes::text($link);
            if ($text !== $link->getUrl()) {
                $entry->text = $text;
            }
            $result[] = $entry;
        }
        return $result;
    }
}
