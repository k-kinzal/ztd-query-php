<?php

declare(strict_types=1);

namespace Deriver\Result\Serialization;

use Deriver\Exception\InvalidInputException;
use stdClass;

/**
 * Preserves arbitrary PHP identifier bytes in otherwise UTF-8 JSON metadata.
 * @visibility public
 * @example Decoding metadata byte tags
 *     $codec = new \Deriver\Result\Serialization\JsonText();
 *     $codec->decode($codec->encode("\xff")) === "\xff" // => true
 */
final class JsonText
{
    /**
     * Encodes invalid UTF-8 and reserved-prefix text with an unambiguous byte tag.
     * @param string $text Original metadata bytes
     * @return string JSON-safe, reversibly encoded text
     */
    public function encode(string $text): string
    {
        return preg_match('//u', $text) === 1 && !str_starts_with($text, '~b64~') ? $text : '~b64~' . base64_encode($text);
    }

    /**
     * Recovers the exact bytes of a metadata string or mapping key.
     * @param string $text Encoded metadata
     * @return string Original bytes
     * @throws InvalidInputException If a byte tag contains invalid Base64
     */
    public function decode(string $text): string
    {
        if (!str_starts_with($text, '~b64~')) {
            return $text;
        }
        $bytes = base64_decode(substr($text, 5), true);
        if ($bytes === false || '~b64~' . base64_encode($bytes) !== $text) {
            throw new InvalidInputException('Invalid Base64 metadata tag.');
        }
        return $bytes;
    }

    /**
     * Encodes a record tree while preserving JSON objects and ordered lists.
     * @param mixed $record Public DTOs, arrays, and scalars
     * @return mixed JSON-safe record tree
     * @throws InvalidInputException If a resource is supplied
     */
    public function tree(mixed $record): mixed
    {
        if (is_string($record)) {
            return $this->encode($record);
        }
        if (is_object($record)) {
            $object = new stdClass();
            foreach (get_object_vars($record) as $key => $value) {
                $object->{$this->encode((string) $key)} = $this->tree($value);
            }
            return $object;
        }
        if (is_array($record)) {
            $array = [];
            foreach ($record as $key => $value) {
                $array[is_string($key) ? $this->encode($key) : $key] = $this->tree($value);
            }
            return $array;
        }
        if (is_scalar($record) || $record === null) {
            return $record;
        }
        throw new InvalidInputException('Resources cannot be represented as metadata.');
    }
}
