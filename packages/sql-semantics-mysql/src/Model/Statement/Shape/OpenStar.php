<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Shape;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A star projection that cannot be expanded because member lists are missing from the context.
 *
 * It stands for an undetermined number of output positions. No placeholder
 * fields are invented for them.
 *
 * @visibility SqlSemantics
 */
final class OpenStar
{
    use Snapshot;

    /**
     * @var non-empty-list<MissingInput> The member lists needed to expand the star
     */
    public readonly array $missing;

    /**
     * @param list<MissingInput> $missing The member lists needed to expand the star; at least one
     */
    public function __construct(array $missing)
    {
        $this->missing = Check::listOf($missing, MissingInput::class, 'An open star names at least one missing input.', 1);
    }
}
