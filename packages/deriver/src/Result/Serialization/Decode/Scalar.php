<?php

declare(strict_types=1);

namespace Deriver\Result\Serialization\Decode;

use Deriver\Exception\InvalidInputException;
use stdClass;

/**
 * Decodes canonical byte strings, target integers, and IEEE binary64 payloads.
 * @visibility root
 */
final class Scalar
{
    /**
     * @param stdClass $record Tagged scalar record
     * @return int|float|string|bool|null Exact PHP scalar
     * @throws InvalidInputException If the scalar tag, payload, or range is invalid
     */
    public function read(stdClass $record): int|float|string|bool|null
    {
        $fields = get_object_vars($record);
        $type = (new Fields())->text($record, 'type');
        if (!array_key_exists('value', $fields)) {
            throw new InvalidInputException('A scalar record requires a value field.');
        }
        $value = $fields['value'];
        if ($type === 'null' && $value === null || $type === 'bool' && is_bool($value)) {
            return $value;
        }
        if (!is_string($value)) {
            throw new InvalidInputException('The scalar payload does not match its tag.');
        }
        return match ($type) {
            'bytes' => $this->bytes($value),
            'int64' => $this->integer($value),
            'float64' => $this->floating($value),
            default => throw new InvalidInputException('Unknown or inconsistent scalar tag: ' . $type),
        };
    }

    /**
     * @param string $text Canonical Base64 payload
     * @return string Original bytes
     * @throws InvalidInputException If the encoding is noncanonical or malformed
     */
    public function bytes(string $text): string
    {
        $bytes = base64_decode($text, true);
        if ($bytes === false || base64_encode($bytes) !== $text) {
            throw new InvalidInputException('Invalid canonical Base64 scalar.');
        }
        return $bytes;
    }

    /**
     * @param string $text Decimal target integer
     * @return int Signed 64-bit integer
     * @throws InvalidInputException If the value exceeds the host or target range
     */
    public function integer(string $text): int
    {
        if (PHP_INT_SIZE !== 8 || preg_match('/\A(?:0|-?[1-9][0-9]{0,18})\z/', $text) !== 1) {
            throw new InvalidInputException('Invalid signed 64-bit integer encoding.');
        }
        $value = (int) $text;
        if ((string) $value !== $text) {
            throw new InvalidInputException('Integer payload is outside the signed 64-bit range.');
        }
        return $value;
    }

    /**
     * @param string $text Sixteen lowercase hexadecimal digits
     * @return float Exact IEEE binary64 value, including signed zero and nonfinite values
     * @throws InvalidInputException If the encoded float is malformed
     */
    public function floating(string $text): float
    {
        if (preg_match('/\A[0-9a-f]{16}\z/', $text) !== 1) {
            throw new InvalidInputException('Invalid IEEE binary64 encoding.');
        }
        $bytes = hex2bin($text);
        if ($bytes === false) {
            throw new InvalidInputException('Invalid hexadecimal float payload.');
        }
        $decoded = unpack('Evalue', $bytes);
        if ($decoded === false || !is_float($decoded['value'] ?? null)) {
            throw new InvalidInputException('Cannot decode the IEEE binary64 payload.');
        }
        return $decoded['value'];
    }
}
