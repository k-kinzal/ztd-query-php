<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;

/**
 * Recognizes the ordinary declared keys that can supply a table's primary identity.
 *
 * @visibility SqlSemantics
 */
final class TableIdentityKeys
{
    /**
     * Answers whether a primary or eligible visible unique declaration already supplies identity.
     * @param list<array{ColumnDefinition|null, Column}> $columns The settled declarations
     */
    public function hasIdentity(CreateTable $definition, array $columns): bool
    {
        $case = new NativeIdentifierCase();
        $required = [];
        foreach ($columns as [$origin, $column]) {
            if ($column->nullability !== Nullability::NotNull || $column->generated) {
                continue;
            }
            $required[$case->fold($column->name->value)] = true;
            if ($origin !== null && (new InlineIdentityKey())->qualifies($origin)) {
                return true;
            }
        }
        foreach ($definition->elements as $element) {
            if (!$element instanceof IndexDefinition) {
                continue;
            }
            if ($element->kind === IndexKind::Primary) {
                return true;
            }
            if ($element->kind !== IndexKind::Unique) {
                continue;
            }
            $visible = true;
            foreach ($element->options as $option) {
                if ($option instanceof IndexVisibility) {
                    $visible = $option->visible;
                }
            }
            if (!$visible) {
                continue;
            }
            foreach ($element->parts as $part) {
                if (!$part instanceof ColumnPart || $part->length !== null
                    || !isset($required[$case->fold($part->column->value)])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }
}
