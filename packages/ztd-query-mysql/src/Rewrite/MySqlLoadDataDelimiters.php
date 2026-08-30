<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\OptionsArray;

/**
 * Reads the file a LOAD DATA is given, as the statement said it is delimited.
 *
 * Nothing about the file is fixed: what ends a field, what ends a record, what
 * a field may be wrapped in and what escapes a byte are all said by the
 * statement, and each of them changes what the bytes mean. A terminator inside
 * something wrapped is part of the value, and an escaped one always is.
 */
final class MySqlLoadDataDelimiters
{
    /**
     * Answers what an option was set to, or what MySQL would use if it was not.
     *
     * @param OptionsArray|null $options Options the statement wrote, or null where it wrote none
     * @param string $name Option to look for
     * @param string $default What MySQL uses when the statement is silent
     *
     * @return string What the statement set it to, or the default
     */
    public function optionValue(?OptionsArray $options, string $name, string $default): string
    {
        if ($options === null) {
            return $default;
        }
        foreach ($options->options as $option) {
            if (!is_array($option)) {
                continue;
            }
            if (($option['name'] ?? null) !== $name) {
                continue;
            }
            $expression = $option['expr'] ?? null;
            if (!$expression instanceof Expression) {
                continue;
            }
            if (!is_string($expression->column)) {
                continue;
            }

            return $expression->column;
        }

        return $default;
    }

    /**
     * Answers the byte an escape stands for.
     *
     * Only a handful of bytes mean something other than themselves after an
     * escape; every other escaped byte is simply itself, which is how a
     * terminator is written inside a value.
     *
     * @param string $byte Byte written after the escape
     *
     * @return string The byte it stands for
     */
    public function escapedByte(string $byte): string
    {
        return match ($byte) {
            '0' => "\0",
            'b' => "\x08",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'Z' => "\x1a",
            default => $byte,
        };
    }

    /**
     * Splits the file into the records it holds.
     *
     * A record terminator inside something wrapped, or written after an escape,
     * is part of a value and does not end the record. A file that does not end
     * with a terminator still ends with a record.
     *
     * @param string $contents The file, as it was read
     * @param string $fieldTerminator What ends a field
     * @param string $lineTerminator What ends a record
     * @param string $enclosure What a field may be wrapped in, or empty where nothing is
     * @param string $escape What escapes a byte, or empty where nothing does
     *
     * @return list<string> The records, still holding their field terminators
     */
    public function records(string $contents, string $fieldTerminator, string $lineTerminator, string $enclosure, string $escape): array
    {
        $records = [];
        $record = '';
        $enclosed = false;
        $atFieldStart = true;

        for ($index = 0; isset($contents[$index]);) {
            $byte = $contents[$index];
            $followingByte = $this->byteAfter($contents, $index);
            if ($escape !== '' && $byte === $escape) {
                if ($followingByte !== null) {
                    $record .= $byte . $followingByte;
                    $index += 2;
                    $atFieldStart = false;
                    continue;
                }
            }
            if ($enclosure !== '' && $byte === $enclosure) {
                if ($enclosed && $followingByte === $enclosure) {
                    $record .= $enclosure . $enclosure;
                    $index += 2;
                    continue;
                }
                if ($enclosed) {
                    $enclosed = false;
                } elseif ($atFieldStart) {
                    $enclosed = true;
                }
                $record .= $enclosure;
                $index++;
                continue;
            }
            if (!$enclosed && $this->startsAt($contents, $lineTerminator, $index)) {
                $records[] = $record;
                $record = '';
                $index += strlen($lineTerminator);
                $atFieldStart = true;
                continue;
            }
            if (!$enclosed && $this->startsAt($contents, $fieldTerminator, $index)) {
                $record .= $fieldTerminator;
                $index += strlen($fieldTerminator);
                $atFieldStart = true;
                continue;
            }
            $record .= $byte;
            $index++;
            $atFieldStart = false;
        }
        if ($record !== '') {
            $records[] = $record;
        }

        return $records;
    }

    /**
     * Splits a record into the values it holds.
     *
     * @param string $record One record, as it was read
     * @param string $terminator What ends a field
     * @param string $enclosure What a field may be wrapped in, or empty where nothing is
     * @param string $escape What escapes a byte, or empty where nothing does
     *
     * @return list<string|null> The values, null where the field says the value is missing
     */
    public function fields(string $record, string $terminator, string $enclosure, string $escape): array
    {
        $fields = [];
        $raw = '';
        $decoded = '';
        $quoted = false;
        $enclosed = false;

        for ($index = 0; isset($record[$index]);) {
            $byte = $record[$index];
            $followingByte = $this->byteAfter($record, $index);
            if ($escape !== '' && $byte === $escape) {
                if ($followingByte !== null) {
                    $raw .= $byte . $followingByte;
                    $decoded .= $this->escapedByte($followingByte);
                    $index += 2;
                    continue;
                }
            }
            if ($enclosure !== '' && $byte === $enclosure) {
                if ($enclosed && $followingByte === $enclosure) {
                    $decoded .= $enclosure;
                    $index += 2;
                    continue;
                }
                if ($enclosed) {
                    $enclosed = false;
                    $index++;
                    continue;
                }
                if (!$quoted) {
                    if ($raw === '') {
                        if ($decoded === '') {
                            $quoted = true;
                            $enclosed = true;
                            $index++;
                            continue;
                        }
                    }
                }
            }
            if (!$enclosed && $this->startsAt($record, $terminator, $index)) {
                $fields[] = $this->fieldValue($raw, $decoded, $quoted, $enclosure, $escape);
                $raw = '';
                $decoded = '';
                $quoted = false;
                $index += strlen($terminator);
                continue;
            }
            $raw .= $byte;
            $decoded .= $byte;
            $index++;
        }
        $fields[] = $this->fieldValue($raw, $decoded, $quoted, $enclosure, $escape);

        return $fields;
    }

    /**
     * Answers what one field of a record says the value is.
     *
     * MySQL writes a missing value as the escape followed by N. The unquoted word
     * NULL means the same thing, but only where nothing could have wrapped or
     * escaped it -- otherwise it is just those four letters.
     *
     * @param string $raw The field, exactly as it was written
     * @param string $decoded The field with its escapes read
     * @param bool $quoted Whether the field was wrapped
     * @param string $enclosure What a field may be wrapped in, or empty where nothing is
     * @param string $escape What escapes a byte, or empty where nothing does
     *
     * @return string|null The value, or null where the field says there is none
     */
    public function fieldValue(string $raw, string $decoded, bool $quoted, string $enclosure, string $escape): ?string
    {
        if ($quoted) {
            return $decoded;
        }
        if ($escape !== '' && $raw === $escape . 'N') {
            return null;
        }
        if ($raw !== 'NULL') {
            return $decoded;
        }
        if ($enclosure !== '') {
            return null;
        }
        if ($escape === '') {
            return null;
        }

        return $decoded;
    }

    /**
     * Reports whether something is written at exactly this position.
     *
     * @param string $value Text to look in
     * @param string $needle Text to look for
     * @param int $offset Position to look at
     *
     * @return bool True when it is written there
     */
    public function startsAt(string $value, string $needle, int $offset): bool
    {
        return substr_compare($value, $needle, $offset, strlen($needle)) === 0;
    }

    /**
     * Answers the byte written after this one.
     *
     * @param string $value Text to look in
     * @param int $offset Position to look after
     *
     * @return string|null The next byte, or null at the end of the text
     */
    public function byteAfter(string $value, int $offset): ?string
    {
        return $value[$offset + 1] ?? null;
    }
}
