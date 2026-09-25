<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Render;

use JsonException;
use Requirements\Config\DocumentReader;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Quotation;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Writes a definition as Markdown cards in the canonical layout.
 *
 * Each card has its badges, statement, evidence quotations and then its fields in a fixed
 * order; badge images, link targets and citations read from the original are reused.
 */
final class CardWriter
{
    /**
     * Writes a definition.
     *
     * @param stdClass $data The definition
     * @param array<string, array<string, array<string, string>>> $links Link targets by item ID, field and linked ID
     * @param array<string, array<string, array<string, array{url: string, title: ?string}>>> $badges Badge images by item ID, field and value
     * @param array<string, list<array{url: string, label: string}|null>> $citations Citation links by item ID
     * @param Citation|null $source Creates citation links for the definition's source
     *
     * @return string The Markdown text
     *
     * @throws InvalidInputException When a record cannot be written as a card
     * @throws JsonException When a metadata value cannot be encoded
     */
    public function render(stdClass $data, array $links, array $badges, array $citations = [], ?Citation $source = null): string
    {
        $header = clone $data;
        unset($header->items);
        $text = "---\n" . DocumentReader::yaml($header) . "---\n";
        $fields = new FieldWriter();
        foreach (Fields::sequence($data->items, 'items') as $entry) {
            $item = Record::fields($entry);
            $id = Fields::text($item, 'id');
            $text .= "\n# $id\n\n";
            $images = [];
            foreach (['kind', 'status', 'origin', 'category'] as $field) {
                if (isset($item[$field])) {
                    $value = Fields::text($item, $field);
                    $images[] = Badges::render($field, $value, $badges[$id][$field][$value] ?? null);
                }
            }
            foreach (Fields::strings($item['labels'] ?? [], 'labels') as $label) {
                $images[] = Badges::render('label', $label, $badges[$id]['label'][$label] ?? null);
            }
            if ($images !== []) {
                $text .= implode("\n", $images) . "\n\n";
            }
            $text .= Nodes::escape(Fields::text($item, 'statement')) . "\n";
            foreach (Fields::sequence($item['evidence'] ?? [], 'evidence') as $index => $evidenceEntry) {
                $evidence = Record::fields($evidenceEntry);
                $text .= "\n" . Quotation::render(Fields::text($evidence, 'selector'), Fields::text($evidence, 'quote'), $source, $citations[$id][$index] ?? null) . "\n";
            }
            foreach (['reason', 'requirements', 'tests', 'related', 'design', 'metadata', 'labels', 'evidence'] as $name) {
                if (!array_key_exists($name, $item) || (in_array($name, ['labels', 'evidence'], true) && $item[$name] !== [])) {
                    continue;
                }
                $heading = $name === 'reason' ? (($item['status'] ?? '') === 'unsupported' ? 'unsupported reason' : 'rationale') : $name;
                $text .= "\n**$heading**\n\n" . $fields->write($name, $item[$name], $links[$id][$name] ?? []) . "\n";
            }
        }
        return $text;
    }
}
