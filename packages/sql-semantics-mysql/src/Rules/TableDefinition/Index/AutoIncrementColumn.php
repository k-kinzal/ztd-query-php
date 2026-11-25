<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;

/**
 * Recognizes the native generating declaration of one physical column.
 * @visibility SqlSemantics
 */
final class AutoIncrementColumn
{
    /**
     * Answers whether the native declaration generates AUTO_INCREMENT values.
     */
    public function matches(ColumnSpecification $specification): bool
    {
        $type = $specification->dataType();
        if ($type instanceof Elementary && $type->kind === ElementaryKind::Serial) {
            return true;
        }
        foreach ($specification->columnAttributes() as $attribute) {
            if ($attribute instanceof KeywordAttribute
                && in_array($attribute->keyword, [ColumnKeyword::AutoIncrement, ColumnKeyword::SerialDefaultValue], true)) {
                return true;
            }
        }

        return false;
    }
}
