<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

/**
 * The completion of a statement that returns no rows: what the server sends in an OK packet.
 *
 * @visibility public
 * @example Reading the rows a write affected
 *     $session = (new \MySqlMemory\Instance())->connect();
 *     $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2)')[2]->affectedRows // => 2
 */
final class Completion implements Reply
{
    /**
     * @param int $affectedRows The number of rows the statement changed, as the server counts them
     * @param int $lastInsertId The first AUTO_INCREMENT value the statement generated, or 0
     * @param int $warnings The number of warnings the statement raised
     * @param string $info The information text, such as `Records: 2  Duplicates: 0  Warnings: 0`
     */
    public function __construct(
        public readonly int $affectedRows = 0,
        public readonly int $lastInsertId = 0,
        public readonly int $warnings = 0,
        public readonly string $info = '',
    ) {
    }

    /**
     * Answers the warning count of the statement.
     */
    #[\Override]
    public function warnings(): int
    {
        return $this->warnings;
    }
}
