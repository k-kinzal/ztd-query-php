<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Command\Show\Keys;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\SchemaError;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;

/**
 * Retains index visibility through definition changes and checks primary keys.
 *
 * The last written visibility wins. An explicit primary key, or the first
 * non-null unique key used as an implicit primary, cannot be invisible.
 * Verified through CREATE TABLE and ALTER INDEX on MySQL 8.0 and 8.4.
 *
 * @visibility MySqlMemory
 */
final class IndexOptions
{
    /**
     * Answers the effective visibility, which defaults to visible.
     */
    public function visible(IndexDefinition $index): bool
    {
        $visible = true;
        foreach ($index->options as $option) {
            if ($option instanceof IndexVisibility) {
                $visible = $option->visible;
            }
        }

        return $visible;
    }

    /**
     * Rejects an invisible explicit or implicit primary key.
     */
    public function check(TableDefinition $table): void
    {
        if ((new Keys())->primary($table)?->visible === false) {
            throw SchemaError::PrimaryKeyInvisible->error();
        }
    }

    /**
     * Replaces visibility without losing the other index options.
     */
    public function change(IndexDefinition $index, bool $visible): IndexDefinition
    {
        $options = array_values(array_filter($index->options, static fn ($option): bool => !$option instanceof IndexVisibility));
        $options[] = new IndexVisibility($visible);

        return new IndexDefinition($index->kind, $index->parts, $index->name, $index->algorithm, $options, $index->constraint, $index->keyword);
    }
}
