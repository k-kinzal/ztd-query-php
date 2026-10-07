<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

/**
 * What the server answers for one statement that succeeds: rows, or a completion.
 *
 * @visibility public
 * @example Telling rows from a completion
 *     $session = (new \MySqlMemory\Instance())->connect();
 *     $session->query('DO 1')[0] instanceof \MySqlMemory\Result\Completion // => true
 */
interface Reply
{
    /**
     * Answers the number of warnings the statement raised.
     */
    public function warnings(): int;
}
