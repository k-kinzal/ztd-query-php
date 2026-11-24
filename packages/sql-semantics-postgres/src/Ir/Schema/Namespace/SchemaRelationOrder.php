<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the exact member phases of a PostgreSQL namespace declaration.
 *
 * @visibility SqlSemantics
 */
final class SchemaRelationOrder
{
    /**
     * Validates the placement and name occupancy of checked roles in execution order.
     * @param list<CreateSequence> $sequences
     * @param list<CreateTable> $tables
     */
    public static function check(?Identifier $name, bool $guard, GrammarRelease $release, array $sequences, array $tables): void
    {
        Check::input(
            ($sequences === [] && $tables === []) || ($name !== null && !$guard),
            'Schema tables and sequences require an unguarded, statically named namespace.'
        );
        $seen = [];
        foreach ($sequences as $sequence) {
            Check::input(
                $sequence->name->schema->value === $name?->value && $sequence->release === $release,
                'A schema sequence must belong to its namespace and release.'
            );
            $coordinate = $sequence->name->name->value;
            Check::input(!isset($seen[$coordinate]) || $sequence->ifNotExists, 'A repeated schema sequence requires its own IF NOT EXISTS guard.');
            $seen[$coordinate] = true;
        }
        foreach ($tables as $table) {
            Check::input(
                $table->target->schema->value === $name?->value && $table->release === $release,
                'A schema table must belong to its namespace and release.'
            );
            $coordinate = $table->target->table->value;
            Check::input(!isset($seen[$coordinate]) || $table->ifNotExists, 'A repeated schema table requires its own IF NOT EXISTS guard.');
            $seen[$coordinate] = true;
        }
    }
}
