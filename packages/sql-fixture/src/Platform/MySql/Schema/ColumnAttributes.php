<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Statement\Scalar;

/**
 * The column attributes that decide how fixture values are generated.
 *
 * Nullability is not read here: the analysis of the statement decides it.
 *
 * @visibility root
 */
final class ColumnAttributes
{
    /**
     * Records the attributes a column declares, defaulting to a plain column without a default literal.
     */
    public function __construct(
        public readonly bool $autoIncrement = false,
        public readonly bool $primaryKey = false,
        public readonly ?Scalar $default = null,
    ) {
    }

    /**
     * Reads the attributes of a column; a DEFAULT expression in parentheses leaves the default unset.
     *
     * @param list<ColumnAttribute> $attributes
     */
    public function read(array $attributes): self
    {
        $autoIncrement = false;
        $primaryKey = false;
        $default = null;
        foreach ($attributes as $attribute) {
            if ($attribute instanceof DefaultLiteral) {
                $default = $attribute->value;
            } elseif ($attribute instanceof DefaultExpression) {
                $default = null;
            } elseif ($attribute instanceof KeywordAttribute) {
                $autoIncrement = $autoIncrement || in_array($attribute->keyword, [ColumnKeyword::AutoIncrement, ColumnKeyword::SerialDefaultValue], true);
                $primaryKey = $primaryKey || $attribute->keyword === ColumnKeyword::PrimaryKey;
            }
        }

        return new self($autoIncrement, $primaryKey, $default);
    }
}
