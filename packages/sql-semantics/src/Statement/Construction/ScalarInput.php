<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

/**
 * A new scalar request, before any relation or alias is resolved.
 * @visibility public
 * @example Specifying new input without an existing query scope
 *     is_a(\SqlSemantics\Statement\Construction\ScalarInput::class, \SqlSemantics\Statement\Operation::class, true) // => false
 */
interface ScalarInput
{
}
