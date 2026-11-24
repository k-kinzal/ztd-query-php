<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclarationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Identity;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\Exclusion;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf;

/**
 * Collects the relation names introduced implicitly by a table declaration.
 *
 * @visibility SqlSemantics
 */
final class TableIndexFacts
{
    /**
     * Finds a definite constraint index and indicates whether other names remain unknown.
     * PostgreSQL can consolidate equivalent constraint indexes during creation.
     *
     * @return array{Name|null, bool, IndexDeclarationKind|null}
     */
    public function collect(CreateTable $table): array
    {
        $constraints = [];
        $unknown = $table->definition instanceof PartitionOf;
        foreach ($table->definition->elements() as $element) {
            if ($element instanceof LikeClause) {
                $unknown = $unknown || $this->copiesNames($element);
            }
            if ($element instanceof ColumnDefinition && (new ColumnTyping())->serial($element->type) !== null) {
                $unknown = true;
            }
            $items = $element instanceof ColumnDefinition || $element instanceof ColumnOptions ? $element->qualifiers : [$element];
            foreach ($items as $item) {
                if ($item instanceof ColumnPrimaryKey || $item instanceof ColumnUnique || $item instanceof TablePrimaryKey || $item instanceof TableUnique || $item instanceof Exclusion) {
                    $constraints[] = [$item->name, $item instanceof Exclusion ? IndexDeclarationKind::ExclusionConstraint : IndexDeclarationKind::KeyConstraint];
                }
                if ($item instanceof Identity || $item instanceof IndexConstraint) {
                    $unknown = true;
                }
            }
        }
        if ($unknown || count($constraints) > 1 || ($constraints !== [] && $constraints[0][0] === null)) {
            return [null, true, null];
        }
        [$name, $kind] = $constraints[0] ?? [null, null];

        return [$name, false, $kind];
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
