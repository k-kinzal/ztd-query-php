<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
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
        $type = $origin->specification->dataType();
        if ($type instanceof Elementary && $type->kind === ElementaryKind::Serial) {
            return true;
        }
        foreach ($origin->specification->columnAttributes() as $attribute) {
            if ($attribute instanceof KeywordAttribute && ($attribute->keyword === ColumnKeyword::PrimaryKey
                || $attribute->keyword === ColumnKeyword::Unique || $attribute->keyword === ColumnKeyword::SerialDefaultValue)) {
                return true;
            }
        }

        return false;
    }
}
