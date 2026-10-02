<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

/**
 * A complete output list contains no matching name.
 * @visibility public
 * @example Distinguishing absence from ambiguity
 *     \SqlSemantics\Statement\Projection\AbsentField::Value instanceof \SqlSemantics\Statement\Projection\AbsentField // => true
 */
enum AbsentField
{
    case Value;
}
