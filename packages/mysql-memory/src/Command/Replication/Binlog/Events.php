<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use Generator;
use MySqlMemory\Error\Family\DataError;

/**
 * Decodes complete events from the BINLOG operand without interpreting SQL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/binlog.html.
 *
 * @visibility MySqlMemory
 */
final class Events
{
    /**
     * @return Generator<int, string, void, void> Complete event bytes, including each common header
     */
    public function read(string $text): Generator
    {
        $compact = (string) preg_replace('/\s+/', '', $text);
        $bytes = strlen($compact) % 4 === 0 ? base64_decode($compact, true) : false;
        if ($bytes === false) {
            throw DataError::Base64DecodeFailed->error();
        }
        $reader = new Reader($bytes);
        do {
            $head = $reader->bytes(13);
            $length = (new Reader(substr($head, 9)))->integer(4);
            yield $head . $reader->bytes(max(0, $length - 13));
        } while ($reader->remaining() !== 0);

    }
}
