<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;

/**
 * Recognizes inline identity declarations on a checked required field.
 *
 * @visibility SqlSemantics
 */
final class InlineIdentityKey
{
    /**
     * Returns whether the required field supplies an ordinary native identity.
     */
    public function qualifies(ColumnDefinition $origin): bool
    {
        return $this->kinds($origin) !== [];
    }

    /**
     * Lists the physical keys created by the column's native flags.
     * @return list<IndexKind>
     */
    public function kinds(ColumnDefinition $origin): array
    {
        $type = $origin->specification->dataType();
        $unique = $type instanceof Elementary && $type->kind === ElementaryKind::Serial;
        $primary = false;
        foreach ($origin->specification->columnAttributes() as $attribute) {
            if ($attribute instanceof KeywordAttribute) {
                $primary = $primary || $attribute->keyword === ColumnKeyword::PrimaryKey;
                $unique = $unique || $attribute->keyword === ColumnKeyword::Unique
                    || $attribute->keyword === ColumnKeyword::SerialDefaultValue;
            }
        }

        return [...($primary ? [IndexKind::Primary] : []), ...($unique ? [IndexKind::Unique] : [])];
    }
}
