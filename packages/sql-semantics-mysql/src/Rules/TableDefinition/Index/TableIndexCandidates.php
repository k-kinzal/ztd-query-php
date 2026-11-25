<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
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
     * Returns key policy, explicit name, first named field and ordinary-part status in native order.
     * @param array<array-key, Name> $columns Physically named columns by native case key
     * @return list<array{IndexKind, ?Name, ?Name, bool}>|null
     */
    public function keys(TableElement $element, array $columns): ?array
    {
        $case = new NativeIdentifierCase();
        if ($element instanceof ColumnDefinition) {
            $field = $columns[$case->fold($element->name->column->value)] ?? null;

            return array_map(
                static fn (IndexKind $kind): array => [$kind, $kind === IndexKind::Primary ? new Name('PRIMARY') : null, $field, true],
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
        $field = $first instanceof ColumnPart ? ($columns[$case->fold($first->column->value)] ?? null) : null;
        $ordinary = true;
        foreach ($element->parts as $part) {
            if (!$part instanceof ColumnPart || $part->length !== null) {
                $ordinary = false;
            } elseif (!isset($columns[$case->fold($part->column->value)])) {
                return null;
            }
        }

        return [[$element->kind, $name, $field, $ordinary]];
    }
}
