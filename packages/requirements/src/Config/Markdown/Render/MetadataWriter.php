<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Render;

use JsonException;
use Requirements\Config\Markdown\Nodes;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Writes metadata as nested lists of "**key:** value" entries or plain values.
 *
 * Scalars are written as JSON, except strings that are not themselves JSON text.
 */
final class MetadataWriter
{
    /**
     * Writes one level of metadata.
     *
     * @param mixed $data A mapping or a sequence
     * @param int $indent The indentation of this level
     *
     * @return string The Markdown list, or {} or [] when empty
     *
     * @throws InvalidInputException When a sequence is not a list
     * @throws JsonException When a scalar cannot be encoded
     */
    public function write(mixed $data, int $indent = 0): string
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
                $text .= $prefix . "\n" . $this->write($value, $indent + 2);
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
}
