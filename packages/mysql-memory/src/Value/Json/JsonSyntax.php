<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use RuntimeException;
use Throwable;

/**
 * A failure to read a JSON text: the message the server gives and the byte position where it was found, which is also its code.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html.
 *
 * @visibility MySqlMemory
 */
final class JsonSyntax extends RuntimeException
{
    /**
     * @param string $reason The message of the JSON parser
     * @param int $position The byte position of the failure in the text
     * @param bool $deep Whether the document nests deeper than the server reads
     * @param Throwable|null $previous The failure this one reports, if any
     */
    public function __construct(public readonly string $reason, public readonly int $position, public readonly bool $deep = false, ?Throwable $previous = null)
    {
        parent::__construct($reason, $position, $previous);
    }
}
