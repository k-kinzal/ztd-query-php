<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

/**
 * Forms of literal information available without evaluating a declaration.
 * @visibility root
 */
enum LiteralKind
{
    case Constant;
    case Array;
    case Enum;
    case Unresolved;
}
