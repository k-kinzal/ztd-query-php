<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\System;

use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The result metadata of sorted reads of the data dictionary's program views.
 *
 * ROUTINES and PARAMETERS pass sorted rows through temporary columns in MySQL 8.x and 9.x.
 * Text expressions become column values with zero decimals, large text is sent as BLOB, temporal
 * values are binary, and key flags disappear. LIMIT 0 keeps the original expression metadata.
 * These distinctions are observed through the client protocol, without reading view definitions.
 *
 * @visibility MySqlMemory
 */
final class ProgramMetadata
{
    /**
     * Answers the metadata after sorting a program view, leaving the execution path and rows intact.
     */
    public static function buffered(QueryPlan $plan, Planner $planner, Select $select, ?Scope $outer, Scope $scope, bool $sorted): QueryPlan
    {
        if (!$sorted || $planner->compiler->settings->legacy() || $planner->blocks->empty($select, $outer)) {
            return $plan;
        }
        foreach ($scope->tables as $definition) {
            $table = $planner->dictionary->system?->table($definition->declaration);
            if ($table?->schema === 'information_schema' && in_array($table->name, ['ROUTINES', 'PARAMETERS'], true)) {
                $domains = array_map(self::domain(...), $plan->domains);

                return new QueryPlan($plan->root, $domains, $plan->names, array_map(self::origin(...), $plan->origins, $domains));
            }
        }

        return $plan;
    }

    /**
     * Answers the type sent for a temporary column holding a program view's value.
     */
    public static function domain(Domain $domain): Domain
    {
        if ($domain->kind->temporal()) {
            return $domain->withCollation(Collation::binary(), $domain->coercibility);
        }
        if ($domain->kind !== Kind::String) {
            return $domain;
        }
        $field = match ($domain->field) {
            Field::TinyBlob, Field::MediumBlob, Field::LongBlob => Field::Blob,
            Field::Enum, Field::Set => $domain->members === [] ? Field::VarString : $domain->field,
            Field::Decimal, Field::Tiny, Field::Short, Field::Long, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::LongLong, Field::Int24, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Blob, Field::VarString, Field::String, Field::Geometry => $domain->field,
        };

        return new Domain($domain->kind, $field, $domain->length, 0, $domain->unsigned, $domain->collation, $domain->nullable, $domain->members, $domain->coercibility);
    }

    /**
     * Keeps column identities while clearing key flags and marking a materialized blob.
     */
    public static function origin(?ColumnOrigin $origin, Domain $domain): ?ColumnOrigin
    {
        if ($origin === null) {
            return null;
        }

        return new ColumnOrigin($origin->schema, $origin->table, $origin->originalTable, $origin->column, $origin->unkeyed()->flags | ($domain->field === Field::Blob ? ColumnFlag::Blob->value : 0), $origin->exact);
    }
}
