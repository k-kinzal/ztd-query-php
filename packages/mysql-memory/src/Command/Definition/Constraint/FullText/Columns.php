<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint\FullText;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Requires character columns for a FULLTEXT index before storage is rebuilt.
 *
 * CHAR, VARCHAR and TEXT are eligible; binary strings, numbers and other types
 * are refused. Source: https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html.
 *
 * @visibility MySqlMemory
 */
final class Columns
{
    /**
     * @param list<ColumnDefinition> $columns The resolved columns of the proposed table
     * @throws SqlError When a FULLTEXT part is not a character column
     */
    public function check(Key $key, array $columns): void
    {
        if ($key->kind !== KeyKind::FullText) {
            return;
        }
        foreach ($key->columns as $position) {
            $column = $columns[$position];
            if (!in_array($column->domain->field, [Field::String, Field::VarString, Field::VarChar, Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true) || $column->domain->collation->charset->name === 'binary') {
                throw SchemaError::FullTextColumn->error($column->name);
            }
        }
    }
}
