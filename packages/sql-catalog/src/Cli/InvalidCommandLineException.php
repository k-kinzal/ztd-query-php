<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

use RuntimeException;

/**
 * The command was given arguments it cannot act on.
 *
 * @visibility root
 */
final class InvalidCommandLineException extends RuntimeException
{
    /**
     * @param string $reason What is wrong with the arguments
     */
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
