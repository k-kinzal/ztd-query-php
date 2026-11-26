<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind;

/**
 * Recognizes table elements that introduce generated relation names.
 *
 * @visibility SqlSemantics
 */
final class TableGeneratedNames
{
    /**
     * Identifies table elements that introduce a name outside the explicit key.
     */
    public function fromElement(Clause $element): bool
    {
        return match (true) {
            $element instanceof LikeClause => $this->copiesNames($element),
            $element instanceof ColumnDefinition => (new ColumnTyping())->serial($element->type) !== null,
            default => false,
        };
    }

    /**
     * Reads the final LIKE options that can create extra relation names.
     */
    public function copiesNames(LikeClause $element): bool
    {
        $indexes = false;
        $identity = false;
        foreach ($element->options as $option) {
            if ($option->kind === LikeOptionKind::All || $option->kind === LikeOptionKind::Indexes) {
                $indexes = $option->including;
            }
            if ($option->kind === LikeOptionKind::All || $option->kind === LikeOptionKind::Identity) {
                $identity = $option->including;
            }
        }

        return $indexes || $identity;
    }
}
