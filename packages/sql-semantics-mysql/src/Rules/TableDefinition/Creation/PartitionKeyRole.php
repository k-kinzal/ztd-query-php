<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation;

use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnFlags;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Checks whether a completely written declaration can supply implicit KEY columns.
 *
 * This is a relation between definition operands, independent of destination
 * existence. Conditional creation may skip it, so a missing key is retained by
 * a complete creation instruction instead of an ordinary table declaration.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PartitionKeyRole
{
    /**
     * @param list<TableElement> $elements The complete written columns and keys
     */
    public function missing(array $elements, ?Partitioning $partitioning, ?TableQuery $query): bool
    {
        if ($query !== null || !$partitioning instanceof PartitionClause) {
            return false;
        }
        $implicit = array_filter([$partitioning->method, $partitioning->subpartitioning?->method], static fn ($method): bool => $method instanceof KeyMethod && $method->columns === []);
        if ($implicit === []) {
            return false;
        }
        $nullable = [];
        $flags = new ColumnFlags();
        foreach ($elements as $element) {
            if (!$element instanceof ColumnDefinition) {
                continue;
            }
            $column = $element->specification;
            $nullable[mb_strtolower($element->name->column->value)] = $flags->nullability($column);
            if ($this->column($element)) {
                return false;
            }
        }
        foreach ($elements as $element) {
            if ($element instanceof IndexDefinition && ($element->kind === IndexKind::Primary || ($element->kind === IndexKind::Unique && $this->candidate($element, $nullable)))) {
                return false;
            }
        }
        return $nullable !== [];
    }

    /**
     * Answers whether an inline column declaration can supply the implicit key.
     */
    public function column(ColumnDefinition $element): bool
    {
        $column = $element->specification;
        $type = $column->dataType();
        $flags = new ColumnFlags();
        return $flags->keyword($column, ColumnKeyword::PrimaryKey)
            || $flags->keyword($column, ColumnKeyword::SerialDefaultValue)
            || ($type instanceof Elementary && $type->kind === ElementaryKind::Serial)
            || ($flags->keyword($column, ColumnKeyword::Unique) && $flags->nullability($column) !== Nullability::Nullable);
    }

    /**
     * Leaves unresolved column names and session-dependent nullability to binding.
     *
     * @param array<string, Nullability> $nullable The written column flags
     */
    public function candidate(IndexDefinition $key, array $nullable): bool
    {
        foreach ($key->parts as $part) {
            if ($part instanceof ColumnPart && ($nullable[mb_strtolower($part->column->value)] ?? Nullability::Dependent) === Nullability::Nullable) {
                return false;
            }
        }
        return true;
    }
}
