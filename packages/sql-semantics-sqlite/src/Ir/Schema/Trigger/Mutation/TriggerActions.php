<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Detaches the ordered action list while verifying its closed value roles.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerActions
{
    /**
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $program The actions submitted to the trigger
     * @return non-empty-list<TriggerInsert|TriggerUpdate|TriggerDelete>
     */
    public function copy(array $program): array
    {
        $actions = [];
        foreach ($program as $step) {
            Check::input($step instanceof TriggerInsert || $step instanceof TriggerUpdate || $step instanceof TriggerDelete, 'A trigger body contains only resolved ordered mutation actions.');
            $actions[] = $step;
        }
        Check::input(array_is_list($program) && $actions !== [], 'A trigger needs a nonempty list of ordered actions.');

        return $actions;
    }
}
