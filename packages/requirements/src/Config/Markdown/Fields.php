<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use Requirements\Input\InvalidInputException;
use stdClass;

final class Fields
{
    /** @var array<string, string> */
    public array $links = [];

    /** @var array<string, string> */
    public array $badges = [];

    /** @param list<Node> $nodes */
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
            'metadata' => $this->mapping($node),
            default => throw new InvalidInputException("Unknown Markdown field '$name'."),
        };
    }

    /** @param list<Node> $nodes */
    private function prose(array $nodes): string
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

    /** @return list<stdClass> */
    private function evidence(Node $node): array
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

    /** @return list<stdClass> */
    private function tests(Node $node): array
    {
        $result = [];
        foreach (Nodes::items($node) as $item) {
            [$runner, $target] = Nodes::pair(Nodes::paragraph($item));
            $result[] = (object) ['runner' => $runner, 'target' => $target];
        }
        return $result;
    }

    /** @return list<string> */
    private function references(Node $node): array
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

    /** @return list<string> */
    private function labels(Node $node): array
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

    /** @return list<stdClass> */
    private function design(Node $node): array
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

    private function mapping(Node $node): stdClass
    {
        $value = $node instanceof Paragraph ? $this->scalar(Nodes::text($node)) : $this->nested($node);
        if (!$value instanceof stdClass) {
            throw new InvalidInputException('Metadata must be a list of bold keys and values.');
        }
        return $value;
    }

    /** @return stdClass|list<mixed> */
    private function nested(Node $node): stdClass|array
    {
        $object = new stdClass();
        $list = [];
        $mapping = null;
        foreach (Nodes::items($node) as $item) {
            $paragraph = $item->firstChild();
            if (!$paragraph instanceof Paragraph) {
                throw new InvalidInputException('Expected a metadata key or value.');
            }
            $isKey = $paragraph->firstChild() instanceof Strong;
            $mapping ??= $isKey;
            if ($mapping !== $isKey) {
                throw new InvalidInputException('Do not mix mapping keys and sequence values in one metadata list.');
            }
            [$key, $text] = $isKey ? Nodes::pair($paragraph) : ['', Nodes::text($paragraph)];
            $child = $paragraph->next();
            if ($child !== null) {
                if (($text !== '' && ($isKey || $text !== '[]')) || $child->next() !== null) {
                    throw new InvalidInputException('Nested metadata must have an empty parent value and one nested list.');
                }
                $value = $this->nested($child);
            } else {
                $value = $this->scalar($text);
            }
            if ($isKey) {
                if ($key === '' || property_exists($object, $key)) {
                    throw new InvalidInputException('Metadata keys must be nonempty and unique.');
                }
                $object->{$key} = $value;
            } else {
                $list[] = $value;
            }
        }
        return $mapping === true ? $object : $list;
    }

    private function scalar(string $text): mixed
    {
        if ($text === '') {
            throw new InvalidInputException('Write "" for an empty string or supply a metadata value.');
        }
        $value = json_decode($text, false);
        return json_last_error() === JSON_ERROR_NONE ? $value : $text;
    }
}
