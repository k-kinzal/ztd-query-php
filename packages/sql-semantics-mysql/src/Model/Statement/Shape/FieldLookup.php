<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Shape;

/**
 * The result of looking an output field up by name: unique, absent, ambiguous, or dependent on missing declarations.
 *
 * @visibility public
 * @example Looking up a name no field has
 *     $operation = (new \SqlSemantics\Platform\MySql\Semantics())->analyze('SELECT 1 AS a');
 *     $operation->lookupField('b') instanceof \SqlSemantics\Platform\MySql\Model\Statement\Shape\AbsentField // => true
 */
interface FieldLookup
{
}
