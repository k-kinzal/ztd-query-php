<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json\Path;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A constant path operand that fails to denote a path, at a decoded byte position.
 *
 * This retains the operand condition; it does not claim execution eligibility.
 *
 * @visibility public
 * @example Locating an invalid path operand
 *     \SqlSemantics\Platform\MySql\Statement\Call\Json\Path::read('bad')->position // => 1
 */
final class Invalid
{
    use Snapshot;

    /**
     * @param int $position The nonnegative byte position in the decoded path operand
     */
    public function __construct(public readonly int $position)
    {
        Check::input($position >= 0, 'A path failure position is nonnegative.');
    }
}
