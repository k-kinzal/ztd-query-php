<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

use Override;

/**
 * The replies one statement answers in turn, as CALL answers a result set for each query of the procedure and a completion last.
 *
 * A session answers each reply of the batch as the reply of a statement of its own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/call.html.
 *
 * @visibility public
 * @example Reading the replies of a batch
 *     $batch = new \MySqlMemory\Result\Batch([new \MySqlMemory\Result\Completion(1)]);
 *     count($batch->replies) // => 1
 */
final class Batch implements Reply
{
    /**
     * @param list<Reply> $replies The replies, in the order they are answered
     */
    public function __construct(public readonly array $replies)
    {
    }

    /**
     * Answers the number of warnings of the last reply.
     */
    #[Override]
    public function warnings(): int
    {
        $last = $this->replies[count($this->replies) - 1] ?? null;

        return $last === null ? 0 : $last->warnings();
    }
}
