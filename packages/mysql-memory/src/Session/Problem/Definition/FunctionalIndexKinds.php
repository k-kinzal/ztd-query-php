<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Definition;

use LogicException;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Reaches an index-kind condition after preceding column operands have resolved.
 *
 * @visibility MySqlMemory
 */
final class FunctionalIndexKinds
{
    /**
     * Preserves ordinary full-text column errors before the first functional part.
     *
     * @param array<string, Domain> $columns The complete proposed column domains
     * @throws SqlError When a preceding column or the retained kind condition fails
     */
    public function check(FunctionalIndexKind $problem, IndexDefinition $index, array $columns, string $engine = 'InnoDB'): never
    {
        if (in_array(strtoupper($engine), ['MEMORY', 'HEAP'], true) && $problem->kind !== IndexKind::Primary) {
            throw $problem->kind === IndexKind::FullText ? SchemaError::FullTextIndexUnsupported->error() : SchemaError::SpatialIndexUnsupported->error();
        }
        foreach ($index->parts as $part) {
            if ($part === $problem->owner) {
                break;
            }
            if (!$part instanceof ColumnPart) {
                continue;
            }
            $column = $columns[mb_strtolower($part->column->value)] ?? null;
            if ($column === null) {
                throw SchemaError::KeyColumnMissing->error($part->column->value);
            }
            if ($column->kind === Kind::Json) {
                throw SchemaError::JsonColumnKey->error($part->column->value);
            }
            if ($problem->kind === IndexKind::FullText && ($column->kind !== Kind::String || !in_array($column->field, [Field::String, Field::VarString, Field::Blob], true) || $column->collation->bytes() || $column->collation->charset->minLength() !== 1)) {
                throw SchemaError::FullTextColumn->error($part->column->value);
            }
        }
        throw $this->error($problem);
    }

    /**
     * Maps the resolved incompatible roles to their SQL condition.
     *
     * @throws LogicException When a condition violates its constructor invariant
     */
    public function error(FunctionalIndexKind $problem): SqlError
    {
        return match ($problem->kind) {
            IndexKind::Primary => ConstraintError::FunctionalPrimary->error(),
            IndexKind::FullText => ConstraintError::FunctionalFullText->error(),
            IndexKind::Spatial => ConstraintError::FunctionalSpatial->error(),
            IndexKind::Index, IndexKind::Unique => throw new LogicException('A functional-kind condition requires a constrained index kind.'),
        };
    }
}
