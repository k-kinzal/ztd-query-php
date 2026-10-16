<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\ErrorCode;

/**
 * A typed input-validation note interleaved with completed grammar notices.
 *
 * @visibility MySqlMemory
 */
final class InputNote
{
    /**
     * @param ErrorCode $code The native condition number and SQLSTATE
     * @param string $message The condition's formatted text
     */
    public function __construct(public readonly ErrorCode $code, public readonly string $message)
    {
    }
}
