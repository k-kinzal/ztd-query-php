<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\LoadData;

/**
 * Record Parser.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class RecordParser
{
    /**
     * @return list<string>
     */
    public function splitRecords(string $contents, InputFormat $format): array
    {
        $records = [];
        $record = '';
        $enclosed = false;
        $atFieldStart = true;

        for ($index = 0; isset($contents[$index]);) {
            $byte = $contents[$index];
            $followingByte = ($contents[$index + 1] ?? null);
            if ($format->escape !== '' && $byte === $format->escape && $followingByte !== null) {
                $record .= $byte . $followingByte;
                $index += 2;
                $atFieldStart = false;
                continue;
            }
            if ($format->enclosure !== '' && $byte === $format->enclosure) {
                $width = $this->enclosureWidth($followingByte, $enclosed, $format->enclosure);
                $record .= str_repeat($format->enclosure, $width);
                if ($width === 1) {
                    $enclosed = !$enclosed && $atFieldStart;
                }
                $index += $width;
                continue;
            }
            if (!$enclosed && (substr_compare($contents, $format->lineTerminator, $index, strlen($format->lineTerminator)) === 0)) {
                $records[] = $record;
                $record = '';
                $index += strlen($format->lineTerminator);
                $atFieldStart = true;
                continue;
            }
            if (!$enclosed && (substr_compare($contents, $format->fieldTerminator, $index, strlen($format->fieldTerminator)) === 0)) {
                $record .= $format->fieldTerminator;
                $index += strlen($format->fieldTerminator);
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
     * @return list<string|null>
     */
    public function parseFields(string $record, InputFormat $format): array
    {
        $fields = [];
        $raw = '';
        $decoded = '';
        $quoted = false;
        $enclosed = false;

        for ($index = 0; isset($record[$index]);) {
            $byte = $record[$index];
            $followingByte = ($record[$index + 1] ?? null);
            if ($format->escape !== '' && $byte === $format->escape && $followingByte !== null) {
                $raw .= $byte . $followingByte;
                $decoded .= $this->decodeEscape($followingByte);
                $index += 2;
                continue;
            }
            if ($format->enclosure !== '' && $byte === $format->enclosure) {
                if ($enclosed && $followingByte === $format->enclosure) {
                    $decoded .= $format->enclosure;
                    $index += 2;
                    continue;
                }
                if ($enclosed) {
                    $enclosed = false;
                    $index++;
                    continue;
                }
                if (!$quoted && $raw === '' && $decoded === '') {
                    $quoted = true;
                    $enclosed = true;
                    $index++;
                    continue;
                }
            }
            if (!$enclosed && (substr_compare($record, $format->fieldTerminator, $index, strlen($format->fieldTerminator)) === 0)) {
                $fields[] = $this->fieldValue($raw, $decoded, $quoted, $format->enclosure, $format->escape);
                $raw = '';
                $decoded = '';
                $quoted = false;
                $index += strlen($format->fieldTerminator);
                continue;
            }
            $raw .= $byte;
            $decoded .= $byte;
            $index++;
        }
        $fields[] = $this->fieldValue($raw, $decoded, $quoted, $format->enclosure, $format->escape);
        return $fields;
    }

    /**
     * Field Value for the supplied MySQL input.
     */
    public function fieldValue(
        string $raw,
        string $decoded,
        bool $quoted,
        string $enclosure,
        string $escape,
    ): ?string {
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
     * Decode MySQL LOAD DATA escape bytes, preserving unrecognized bytes.
     */
    public function decodeEscape(string $byte): string
    {
        return match ($byte) {
            '0' => "\x00", 'b' => "\x08", 'n' => "\n", 'r' => "\r",
            't' => "\t", 'Z' => "\x1a", default => $byte,
        };
    }

    /**
     * A doubled enclosure inside an enclosed field is one literal enclosure token.
     */
    public function enclosureWidth(?string $followingByte, bool $enclosed, string $enclosure): int
    {
        return $enclosed && $followingByte === $enclosure ? 2 : 1;
    }

}
