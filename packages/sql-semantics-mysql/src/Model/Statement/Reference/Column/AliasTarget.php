<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Column;

use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A name that resolves to an output field of the same query by its alias.
 *
 * The reference stays a reference: the aliased expression is not copied to the use position.
 *
 * @visibility SqlSemantics
 */
final class AliasTarget implements Resolution
{
    use Snapshot;

    /**
     * @param Field $field The output field the alias names
     */
    public function __construct(public readonly Field $field)
    {
    }
}
