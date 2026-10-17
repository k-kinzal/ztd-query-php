<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Resolves a positive key prefix against the current column's storage domain.
 *
 * Full-text indexes ignore positive prefixes. Ordinary indexes require a
 * string field; a fixed-capacity string cannot have a longer prefix. Spatial
 * indexes keep their separate engine and geometry diagnostics.
 *
 * @visibility MySqlMemory
 */
final class ColumnPrefixes
{
    /**
     * Answers the effective prefix length after resolving the indexed column.
     *
     * @throws SqlError When the current column cannot supply the requested prefix
     */
    public function length(ColumnPart $part, Domain $domain, IndexKind $kind): ?int
    {
        if ($part->length === null || $kind === IndexKind::FullText) {
            return null;
        }
        $length = (int) $part->length->text;
        if ($kind === IndexKind::Spatial) {
            return $length;
        }
        $fixed = in_array($domain->field, [Field::String, Field::VarChar, Field::VarString], true);
        if (($fixed && $length > $domain->length) || (!$fixed && !in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true))) {
            throw SchemaError::IncorrectPrefixKey->error();
        }

        return $length;
    }
}
