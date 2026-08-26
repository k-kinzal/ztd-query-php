<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

/**
 * Writes a statement so a driver reads no placeholder that was not meant.
 *
 * A rewritten statement carries values inline that the original carried as
 * parameters, and text among them may look to the driver like a placeholder
 * of its own.
 */
interface SqlPlaceholderEscaper
{
    public function escape(string $sql): string;
}
