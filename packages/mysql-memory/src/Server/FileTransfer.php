<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use LogicException;

/**
 * Receives a client file while the statement that requested it is suspended.
 *
 * File packets continue the query sequence and an empty packet ends the upload.
 * The listener can serve other connections while this connection supplies bytes.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_local_infile_request.html.
 *
 * @visibility MySqlMemory
 */
final class FileTransfer
{
    /**
     * Whether incoming packets belong to an upload rather than a command.
     */
    public bool $active = false;

    /**
     * Whether the client has sent the empty terminating packet.
     */
    public bool $complete = false;

    private string $contents = '';

    /**
     * @param Client $client The connection receiving the file
     */
    public function __construct(public readonly Client $client)
    {
    }

    /**
     * Requests exactly the file named by LOAD and waits for the client's response.
     *
     * @throws LogicException When a caller outside the listener cannot wait for input
     */
    public function request(string $file): string
    {
        $this->active = true;
        $this->complete = false;
        $this->contents = '';
        try {
            $this->client->packet("\xFB" . $file);
            while ($this->waiting()) {
                $scheduler = $this->client->instance->transactions->scheduler;
                if (!$scheduler->suspendable()) {
                    throw new LogicException('A client file transfer requires a listener fiber.');
                }
                $scheduler->pause();
            }
            return $this->contents;
        } finally {
            $this->active = false;
            $this->contents = '';
        }
    }

    /**
     * Accepts a complete file packet, including the empty terminator.
     */
    public function receive(string $payload): void
    {
        $this->contents .= $payload;
        $this->complete = $payload === '';
    }

    /**
     * Tells the suspended statement whether the listener still needs file packets.
     */
    public function waiting(): bool
    {
        return $this->active && !$this->complete;
    }
}
