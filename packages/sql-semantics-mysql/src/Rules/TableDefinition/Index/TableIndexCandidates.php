<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

/**
 * Identifies the index definitions made by one table element.
 *
 * @visibility SqlSemantics
 */
final class TableIndexCandidates
{
    /**
     * Returns key policy, optional names, ordinary-part status and clustered eligibility.
     * @param array<array-key, array{Name, bool, bool}> $columns Physical names, required fields and generation flags by native case key
     * @return list<array{IndexKind, ?Name, ?Name, bool, bool, ?Name}>|null
     */
    public function keys(TableElement $element, array $columns): ?array
    {
        $case = new NativeIdentifierCase();
        if ($element instanceof ColumnDefinition) {
            [$field, $required, $incremented] = $columns[$case->fold($element->name->column->value)] ?? [null, false, false];

            return array_map(
                static fn (IndexKind $kind): array => [$kind, $kind === IndexKind::Primary ? new Name('PRIMARY') : null,
                    $field, true, $kind === IndexKind::Unique && $required, $incremented ? $field : null],
                (new InlineIdentityKey())->kinds($element)
            );
        }
        if (!$element instanceof IndexDefinition) {
            return [];
        }

        $name = $element->kind === IndexKind::Primary
            ? new Name('PRIMARY')
            : ($element->name ?? $element->constraint?->name)?->column;
        $first = $element->parts[0];
        $leading = $first instanceof ColumnPart ? ($columns[$case->fold($first->column->value)] ?? null) : null;
        [$field, , $incremented] = $leading ?? [null, false, false];
        $support = $incremented && $first instanceof ColumnPart && $first->length === null ? $field : null;
        $ordinary = true;
        $required = true;
        foreach ($element->parts as $part) {
            if (!$part instanceof ColumnPart) {
                $ordinary = false;
                continue;
            }
            $facts = $columns[$case->fold($part->column->value)] ?? null;
            if ($facts === null) {
                return null;
            }
            $ordinary = $ordinary && $part->length === null;
            $required = $required && $facts[1];
        }

        return [[$element->kind, $name, $field, $ordinary, $element->kind === IndexKind::Unique && $ordinary && $required, $support]];
    }

    /**
     * Resolves the names and the eligibility of full required, nonvirtual key fields.
     * @param list<array{ColumnDefinition|null, Column}> $columns Effective ordered column definitions
     * @return array<array-key, array{Name, bool, bool}>|null
     */
    public function fields(array $columns): ?array
    {
        $case = new NativeIdentifierCase();
        $byName = [];
        foreach ($columns as [$origin, $column]) {
            $key = $case->fold($column->name->value);
            if (isset($byName[$key])) {
                return null;
            }
            $specification = $origin?->specification;
            $required = $column->nullability === Nullability::NotNull
                && (!$specification instanceof GeneratedColumn || $specification->storage === GeneratedStorage::Stored);
            $incremented = $specification !== null && (new AutoIncrementColumn())->matches($specification);
            $byName[$key] = [$column->name, $required, $incremented];
        }

        return $byName;
    }
}
