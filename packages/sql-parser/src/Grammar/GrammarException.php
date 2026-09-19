<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

use RuntimeException;

/**
 * A grammar is not well formed: its symbols or rules contradict each other.
 *
 * @visibility root
 */
class GrammarException extends RuntimeException
{
}
