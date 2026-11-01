<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Error\Family\StatementError;

/**
 * Decodes packed temporal values, retaining zero dates and fractional precision.
 * Timestamps are held in UTC, matching the storage layer's representation.
 * Source: https://mariadb.com/docs/server/reference/clientserver-protocol/replication-protocol/rows_event_v1v2-rows_compressed_event_v1.
 *
 * @visibility MySqlMemory
 */
final class TemporalStorage
{
    /**
     * Reads a three-byte year/month/day field.
     */
    public function date(Reader $reader): string
    {
        $packed = $reader->integer(3);

        return sprintf('%04d-%02d-%02d', $packed >> 9, ($packed >> 5) & 15, $packed & 31);
    }

    /**
     * Reads a modern fractional temporal field using its table-map precision.
     */
    public function read(Reader $reader, int $type, string $metadata): string
    {
        $precision = (new Reader($metadata))->integer(1);
        if ($precision > 6) {
            throw StatementError::SyntaxError->error();
        }
        $width = intdiv($precision + 1, 2);
        $packed = $reader->integer(match ($type) {
            17 => 4, 18 => 5, default => 3
        }, true);
        $fraction = $reader->integer($width, true);
        if ($type === 17) {
            $text = $packed === 0 ? '0000-00-00 00:00:00' : gmdate('Y-m-d H:i:s', $packed);
        } elseif ($type === 18) {
            $text = $this->dateTime($packed - 0x8000000000);
        } else {
            $packed -= 0x800000;
            $negative = $packed < 0;
            if ($negative && $fraction !== 0) {
                $packed++;
                $fraction = (1 << ($width * 8)) - $fraction;
            }
            $packed = abs($packed);
            $text = ($negative ? '-' : '') . sprintf('%02d:%02d:%02d', $packed >> 12, ($packed >> 6) & 63, $packed & 63);
        }
        $microseconds = $fraction * 10 ** (6 - $width * 2);

        return $text . ($precision === 0 ? '' : '.' . substr(sprintf('%06d', $microseconds), 0, $precision));
    }

    /**
     * Formats the year/month/day and hour/minute/second bit fields.
     */
    public function dateTime(int $packed): string
    {
        $date = $packed >> 17;
        $yearMonth = $date >> 5;

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', intdiv($yearMonth, 13), $yearMonth % 13, $date & 31, ($packed >> 12) & 31, ($packed >> 6) & 63, $packed & 63);
    }

    /**
     * Reads temporal fields from the pre-fractional row encoding.
     */
    public function legacy(Reader $reader, int $type): string
    {
        if ($type === 7) {
            $seconds = $reader->integer(4);
            return $seconds === 0 ? '0000-00-00 00:00:00' : gmdate('Y-m-d H:i:s', $seconds);
        }
        if ($type === 12) {
            $digits = str_pad((string) $reader->integer(8), 14, '0', STR_PAD_LEFT);
            return substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2) . ' ' . substr($digits, 8, 2) . ':' . substr($digits, 10, 2) . ':' . substr($digits, 12, 2);
        }
        $number = (new RowImage())->integer($reader, 3, false);
        $digits = str_pad((string) abs($number), 6, '0', STR_PAD_LEFT);

        return ($number < 0 ? '-' : '') . substr($digits, 0, -4) . ':' . substr($digits, -4, 2) . ':' . substr($digits, -2);
    }
}
