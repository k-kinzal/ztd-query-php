<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Error\Family\StatementError;

/**
 * The event layout retained by a connection after a format description.
 * This is runtime replication state, separate from the SQL semantic structure.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_replication_binlog_event.html.
 *
 * @visibility MySqlMemory
 */
final class Format
{
    /**
     * @param int $headerLength Common event-header size
     * @param string $headers Post-header sizes indexed by event type minus one
     * @param int $checksumLength Trailing checksum bytes in subsequent events
     */
    public function __construct(public readonly int $headerLength, public readonly string $headers, public readonly int $checksumLength)
    {
    }

    /**
     * Reads a format description. BINLOG does not verify the supplied checksum;
     * it retains the checksum algorithm to delimit following event bodies.
     * Verified with original and corrupted native events on MySQL 9.1.0.
     */
    public static function read(string $event): self
    {
        $reader = new Reader($event);
        $reader->bytes(21);
        $version = rtrim($reader->bytes(50), "\0");
        $reader->bytes(4);
        $header = $reader->integer(1);
        if ($header < 19) {
            throw StatementError::SyntaxError->error();
        }
        $checksummed = version_compare($version, '5.6.1', '>=');
        $headers = $reader->bytes($reader->remaining() - ($checksummed ? 5 : 0));
        $checksum = $checksummed ? $reader->integer(1) : 0;

        return new self($header, $headers, $checksum === 1 ? 4 : 0);
    }

    /**
     * Reads an event's body with its common header and checksum removed.
     */
    public function body(string $event): Reader
    {
        $reader = new Reader($event);
        $reader->bytes($this->headerLength);

        return new Reader($reader->bytes($reader->remaining() - $this->checksumLength));
    }

    /**
     * Answers the post-header size advertised for an event type.
     */
    public function postHeader(int $type): int
    {
        return $type > 0 && $type <= strlen($this->headers) ? ord($this->headers[$type - 1]) : 0;
    }
}
