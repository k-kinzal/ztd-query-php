<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Hint;

use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError;

/**
 * Stops the reading of a hint comment at its first problem; the parser catches it and keeps the hints read before.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Hint
 */
final class HintRefusal extends RuntimeException
{
    /**
     * @param HintError $error The problem the comment stops at
     */
    public function __construct(public readonly HintError $error)
    {
        parent::__construct($error->failure->value);
    }
}
