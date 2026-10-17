<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\Family\DataError;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Applies NOT NULL insertion rules after the column's storage conversion.
 *
 * MySQL 5.7 refuses NULL geometry VALUES even with IGNORE, while INSERT IGNORE ... SELECT
 * stores the implicit empty geometry. The caller handles an ignored refusal at statement level.
 *
 * @visibility MySqlMemory
 */
final class InsertNulls
{
    /**
     * Keeps an admissible value, substitutes an implicit value with a warning, or refuses NULL.
     *
     * @throws \MySqlMemory\Error\SqlError When a required column refuses NULL
     */
    public function value(Writer $writer, ColumnDefinition $column, int|float|string|null $value, bool $single, bool $ignore, bool $queried): int|float|string|null
    {
        if ($value !== null || $column->nullable() || $column->autoIncrement) {
            return $value;
        }
        $geometry = $column->domain->field === Field::Geometry && $writer->context->modes->release === GrammarRelease::MySql5744;
        if (($single && !$ignore) || $writer->context->strict || ($geometry && (!$queried || !$ignore))) {
            throw DataError::BadNull->error($column->name);
        }
        $writer->context->warning(DataError::BadNull, $column->name);

        return $writer->implicit($column);
    }
}
