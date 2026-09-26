<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Render;

use JsonException;
use Requirements\Config\Markdown\Nodes;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * Writes the value of one bold field section of a card.
 *
 * Prose is escaped text, metadata a nested list, and list fields bullet lists or "None."
 * when empty.
 */
final class FieldWriter
{
    /**
     * Writes a field value.
     *
     * @param string $name The field name
     * @param mixed $value The value in the shape of the YAML field
     * @param array<string, string> $links Link targets read for this field by linked ID
     *
     * @return string The Markdown section body
     *
     * @throws InvalidInputException When the value cannot be written for that field
     * @throws JsonException When a metadata value cannot be encoded
     */
    public function write(string $name, mixed $value, array $links): string
    {
        if (is_string($value)) {
            return Nodes::escape($value);
        }
        if ($name === 'metadata') {
            return rtrim((new MetadataWriter())->write($value));
        }
        $rows = [];
        foreach (Fields::sequence($value, $name) as $entry) {
            if (in_array($name, ['requirements', 'related'], true) && is_string($entry)) {
                $rows[] = '- [' . Nodes::escape($entry) . '](' . Nodes::destination($links[$entry] ?? ('#' . Nodes::anchor($entry))) . ')';
            } else {
                $record = Record::fields($entry);
                if ($name === 'tests') {
                    $rows[] = '- **' . Nodes::escape(Fields::text($record, 'runner')) . ':** ' . Nodes::escape(Fields::text($record, 'target'));
                } elseif ($name === 'design') {
                    $rows[] = isset($record['url']) ? '- [' . Nodes::escape(Fields::text($record, 'text', Fields::text($record, 'url'))) . '](' . Nodes::destination(Fields::text($record, 'url')) . ')' : '- ' . Nodes::escape(Fields::text($record, 'text'));
                } else {
                    throw new InvalidInputException("Cannot render Markdown field '$name'.");
                }
            }
        }
        return $rows === [] ? 'None.' : implode("\n", $rows);
    }
}
