<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

/**
 * Fixed relationships a window cannot have with the definition it refines.
 *
 * @example Naming the dependent partition constraint
 *     \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Partition->value // => 'A dependent window cannot define a partition.'
 *
 * @visibility public
 */
enum WindowRefinementKind: string
{
    case Partition = 'A dependent window cannot define a partition.';
    case Ordering = 'A dependent window cannot replace inherited ordering.';
    case Frame = 'A window with an explicit frame cannot be inherited.';
}
