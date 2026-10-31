<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Compares the character set a column type names with the collation its COLLATE attribute names.
 *
 * Rule: MYSQL-COLUMN-COLLATIONS-001. A column written with both CHARACTER SET and COLLATE
 * names a collation of that character set; the server refuses another pairing while reading
 * the definition, even inside a stored program body (ER_COLLATION_CHARSET_MISMATCH; verified on
 * 5.6.51, 5.7.44 and 8.4.7). A type without a named character set, or a COLLATE without a name,
 * is compared later against the table or database default. Terminates: one pass over the
 * attributes. Source: https://dev.mysql.com/doc/refman/8.4/en/charset-column.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnCollations
{
    /**
     * Answers the mismatch of a column whose named collation belongs to another character set than the named one.
     */
    public function mismatch(ColumnDefinition $column, GrammarRelease $release): ?CollationMismatch
    {
        $specification = $column->specification;
        if (!$specification instanceof OrdinaryColumn && !$specification instanceof GeneratedColumn) {
            return null;
        }
        $attribute = get_object_vars($specification->dataType())['charset'] ?? null;
        $charset = $attribute instanceof CharsetAttribute && $attribute->charset !== null ? Charset::named($attribute->charset->value) : null;
        if ($charset === null) {
            return null;
        }
        foreach ($specification->attributes as $written) {
            if ($written instanceof CollateAttribute && $written->collation->name !== null) {
                $collation = Collation::forName($written->collation->name->value, $release);
                if ($collation !== null && $collation->charset->name !== $charset->name) {
                    return new CollationMismatch($collation->nameIn($release), $charset->name);
                }
            }
        }

        return null;
    }
}
