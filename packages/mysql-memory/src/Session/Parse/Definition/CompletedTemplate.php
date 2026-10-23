<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use RuntimeException;

/**
 * Returns the completion of a conditional definition retained at the input boundary.
 * The result contains no SQL text or parser tree.
 *
 * @visibility MySqlMemory
 */
final class CompletedTemplate extends RuntimeException
{
    /**
     * @param \MySqlMemory\Result\Reply $reply The completion of the retained request
     */
    public function __construct(public readonly \MySqlMemory\Result\Reply $reply)
    {
        parent::__construct('The retained conditional definition completed.');
    }
}
