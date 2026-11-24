<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;

/**
 * Allocates one complete native row layout for a bound trigger destination.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerTargetLowering
{
    /**
     * Resolves the ordinary destination before any positional writes are constructed.
     *
     * @return array{SourceBinding, TriggerDestination}
     * @throws InvalidConstruction When there is no complete declared write target
     */
    public function lower(BoundStatement $operation, MutationTarget $statement): array
    {
        $resolved = $operation->facts->relation($statement)->table;
        Check::input($resolved instanceof DeclaredTable && $resolved->table->complete, 'A trigger mutation needs a complete destination declaration.');
        $binding = new SourceBinding($operation, $statement);
        foreach ($resolved->table->columns as $column) {
            $binding->slot(0, $column);
        }
        $target = $binding->source();
        Check::input($target instanceof TableSource, 'A trigger mutation needs a resolved destination.');
        $available = [];
        foreach ($binding->implicitWrites() as $slot) {
            $available[] = $slot->slot;
        }

        return [$binding, new TriggerDestination($target, $available)];
    }
}
