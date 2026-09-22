<?php

declare(strict_types=1);

namespace Requirements\Markdown;

use InvalidArgumentException;
use Requirements\Config\DocumentReader;
use Requirements\Config\Fields;
use stdClass;

final class Writer
{
    /**
     * @param array<string, array<string, array<string, string>>> $links
     * @param array<string, array<string, string>> $badges
     */
    public function render(stdClass $data, array $links, array $badges): string
    {
        $header = clone $data;
        unset($header->items);
        $text = "---\n" . DocumentReader::yaml($header) . "---\n";
        foreach (Fields::sequence($data->items, 'items') as $entry) {
            $item = $this->object($entry);
            $id = Fields::text($item, 'id');
            $text .= "\n# $id\n\n" . Nodes::escape(Fields::text($item, 'statement')) . "\n";
            foreach ($item as $name => $value) {
                if (in_array($name, ['id', 'statement'], true)) {
                    continue;
                }
                $text .= "\n**$name**\n\n" . $this->field($name, $value, $links[$id][$name] ?? [], $badges[$id] ?? null) . "\n";
            }
        }
        return $text;
    }

    /**
     * @param array<string, string> $links
     * @param array<string, string>|null $badges
     */
    private function field(string $name, mixed $value, array $links, ?array $badges): string
    {
        if (is_string($value)) {
            return Nodes::escape($value);
        }
        if ($name === 'metadata') {
            return rtrim($this->metadata($value));
        }
        $rows = [];
        foreach (Fields::sequence($value, $name) as $entry) {
            if ($name === 'labels' && is_string($entry)) {
                $url = $badges[$entry] ?? ('https://img.shields.io/badge/label-' . rawurlencode(str_replace('-', '--', $entry)) . '-blue');
                $rows[] = $badges === [] ? '- ' . Nodes::escape($entry) : '![' . Nodes::escape($entry) . '](' . Nodes::destination($url) . ')';
            } elseif (in_array($name, ['requirements', 'related'], true) && is_string($entry)) {
                $rows[] = '- [' . Nodes::escape($entry) . '](' . Nodes::destination($links[$entry] ?? ('#' . Nodes::anchor($entry))) . ')';
            } else {
                $record = $this->object($entry);
                if ($name === 'evidence') {
                    $rows[] = '- **selector:** ' . Nodes::escape(Fields::text($record, 'selector')) . "\n\n  > " . str_replace("\n", "\n  > ", Nodes::escape(Fields::text($record, 'quote')));
                } elseif ($name === 'tests') {
                    $rows[] = '- **' . Nodes::escape(Fields::text($record, 'runner')) . ':** ' . Nodes::escape(Fields::text($record, 'target'));
                } elseif ($name === 'design') {
                    $rows[] = isset($record['url']) ? '- [' . Nodes::escape(Fields::text($record, 'text', Fields::text($record, 'url'))) . '](' . Nodes::destination(Fields::text($record, 'url')) . ')' : '- ' . Nodes::escape(Fields::text($record, 'text'));
                } else {
                    throw new InvalidArgumentException("Cannot render Markdown field '$name'.");
                }
            }
        }
        if ($rows === []) {
            return 'None.';
        }
        return implode($name === 'labels' && $badges !== [] ? ' ' : "\n", $rows);
    }

    private function metadata(mixed $data, int $indent = 0): string
    {
        $object = $data instanceof stdClass;
        $values = $object ? get_object_vars($data) : Fields::sequence($data, 'metadata sequence');
        if ($values === []) {
            return $object ? '{}' : '[]';
        }
        $text = '';
        foreach ($values as $key => $value) {
            $prefix = str_repeat(' ', $indent) . '- ' . ($object ? '**' . Nodes::escape((string) $key) . ':**' : '');
            if (($value instanceof stdClass && get_object_vars($value) !== []) || (is_array($value) && $value !== [])) {
                if (!$object) {
                    $prefix .= '[]';
                }
                $text .= $prefix . "\n" . $this->metadata($value, $indent + 2);
            } else {
                $scalar = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if (is_string($value) && $value !== '' && json_decode($value, false) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $scalar = $value;
                }
                $text .= $prefix . ($object ? ' ' : '') . Nodes::escape($scalar) . "\n";
            }
        }
        return $text;
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        if (!$value instanceof stdClass) {
            throw new InvalidArgumentException('Expected a Markdown record.');
        }
        return Fields::mapping(get_object_vars($value), 'record');
    }
}
