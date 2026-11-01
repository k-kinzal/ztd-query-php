<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access\Load;

/**
 * Decodes client file records using the separators held by LOAD DATA.
 * Enclosed fields can contain record delimiters; escaped delimiters are data.
 * The file is runtime input, independent of the SQL parser and source text.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility MySqlMemory
 */
final class SeparatedRecords
{
    /**
     * @param array<string, string> $separators Effective semantic field and line options
     * @return list<non-empty-list<string|null>> Decoded records, preserving missing and surplus fields
     */
    public function read(string $input, array $separators, int $ignored = 0): array
    {
        $field = $separators['field:TERMINATED'] ?? "\t";
        $line = $separators['line:TERMINATED'] ?? "\n";
        $enclosure = $separators['field:ENCLOSED'] ?? '';
        $escape = $separators['field:ESCAPED'] ?? '\\';
        $prefix = $separators['line:STARTING'] ?? '';
        $offset = 0;
        while ($ignored-- > 0 && $offset < strlen($input)) {
            $end = $line === '' ? false : strpos($input, $line, $offset);
            $offset = $end === false ? strlen($input) : $end + strlen($line);
        }
        $records = [];
        while ($offset < strlen($input)) {
            $offset = $this->start($input, $offset, $prefix, $line);
            if ($offset >= strlen($input)) {
                break;
            }
            $values = [];
            do {
                [$value, $offset, $ended] = $this->field($input, $offset, $field, $line, $enclosure, $escape);
                $values[] = $value;
            } while (!$ended);
            $records[] = $values;
        }
        return $records;
    }

    /**
     * Finds a row prefix, discarding physical lines without it.
     */
    public function start(string $input, int $offset, string $prefix, string $line): int
    {
        if ($prefix === '') {
            return $offset;
        }
        while ($offset < strlen($input)) {
            $found = strpos($input, $prefix, $offset);
            if ($found === false) {
                return strlen($input);
            }
            $end = $line === '' ? false : strpos($input, $line, $offset);
            if ($end === false || $found <= $end) {
                return $found + strlen($prefix);
            }
            $offset = $end + strlen($line);
        }
        return $offset;
    }

    /**
     * @return array{string|null, int, bool} Value, next byte offset and whether the record ended
     */
    public function field(string $input, int $offset, string $field, string $line, string $enclosure, string $escape): array
    {
        $quoted = $enclosure !== '' && ($input[$offset] ?? '') === $enclosure;
        $offset += (int) $quoted;
        $raw = $value = '';
        while ($offset < strlen($input)) {
            $byte = $input[$offset];
            if ($escape !== '' && $byte === $escape && $offset + 1 < strlen($input)) {
                $next = $input[$offset + 1];
                $raw .= $byte . $next;
                $value .= $this->escaped($next);
                $offset += 2;
                continue;
            }
            if ($quoted && $byte === $enclosure) {
                if (($input[$offset + 1] ?? '') === $enclosure) {
                    $raw .= $enclosure . $enclosure;
                    $value .= $enclosure;
                    $offset += 2;
                    continue;
                }
                $end = $this->ending($input, $offset + 1, $field, $line);
                if ($end !== null) {
                    return [$this->value($raw, $value, true, $enclosure, $escape), $end[0], $end[1]];
                }
            }
            $end = $quoted ? null : $this->ending($input, $offset, $field, $line);
            if ($end !== null) {
                return [$this->value($raw, $value, false, $enclosure, $escape), $end[0], $end[1]];
            }
            $raw .= $byte;
            $value .= $byte;
            $offset++;
        }
        return [$this->value($raw, $value, $quoted, $enclosure, $escape), $offset, true];
    }

    /**
     * @return array{int, bool}|null The offset after the separator and whether it ends a record
     */
    public function ending(string $input, int $offset, string $field, string $line): ?array
    {
        if ($offset === strlen($input)) {
            return [$offset, true];
        }
        if ($line !== '' && substr_compare($input, $line, $offset, strlen($line)) === 0) {
            return [$offset + strlen($line), true];
        }
        if ($field !== '' && substr_compare($input, $field, $offset, strlen($field)) === 0) {
            return [$offset + strlen($field), false];
        }
        return null;
    }

    /**
     * Distinguishes the NULL marker from quoted NULL and escaped backslashes.
     */
    public function value(string $raw, string $value, bool $quoted, string $enclosure, string $escape): ?string
    {
        return ($escape !== '' && $raw === $escape . 'N') || (!$quoted && $enclosure !== '' && $raw === 'NULL') ? null : $value;
    }

    /**
     * Decodes a byte following the configured escape character.
     */
    public function escaped(string $byte): string
    {
        return match ($byte) {
            '0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a", default => $byte
        };
    }
}
