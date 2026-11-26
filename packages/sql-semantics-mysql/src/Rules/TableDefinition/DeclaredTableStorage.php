<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;

/**
 * Resolves the decoded native engine from a table definition.
 *
 * @visibility SqlSemantics
 */
final class DeclaredTableStorage
{
    /**
     * Uses the last explicit engine option or the stock InnoDB default.
     */
    public function resolve(CreateTable $definition): string
    {
        $engine = 'InnoDB';
        foreach ($definition->options as $option) {
            if ($option instanceof EngineOption) {
                $engine = $option->engine->value;
            }
        }

        return $engine;
    }
}
