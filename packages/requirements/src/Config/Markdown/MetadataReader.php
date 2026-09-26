<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Reads the metadata field: nested lists of "**key:** value" entries or plain values.
 *
 * A value is decoded as JSON when it is valid JSON and kept as text otherwise; a parent
 * entry with an empty value (or [] in a sequence) holds one nested list.
 */
final class MetadataReader
{
    /**
     * Reads the metadata mapping.
     *
     * @param Node $node A paragraph holding a JSON object, or a bullet list of keys
     *
     * @return stdClass The metadata
     *
     * @throws InvalidInputException When the value is not a mapping or the lists are malformed
     */
    public function read(Node $node): stdClass
    {
        $value = $node instanceof Paragraph ? $this->scalar(Nodes::text($node)) : $this->nested($node);
        if (!$value instanceof stdClass) {
            throw new InvalidInputException('Metadata must be a list of bold keys and values.');
        }
        return $value;
    }

    /**
     * Reads one level of nested metadata.
     *
     * @param Node $node The bullet list
     *
     * @return stdClass|list<mixed> A mapping for bold keys, a sequence for plain values
     *
     * @throws InvalidInputException When keys and values are mixed, a key repeats or a nested list is misplaced
     */
    public function nested(Node $node): stdClass|array
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

    /**
     * Reads a scalar value.
     *
     * @param string $text The written value
     *
     * @return mixed The decoded JSON value, or the text when it is not JSON
     *
     * @throws InvalidInputException When the value is empty
     */
    public function scalar(string $text): mixed
    {
        if ($text === '') {
            throw new InvalidInputException('Write "" for an empty string or supply a metadata value.');
        }
        $value = json_decode($text, false);
        return json_last_error() === JSON_ERROR_NONE ? $value : $text;
    }
}
