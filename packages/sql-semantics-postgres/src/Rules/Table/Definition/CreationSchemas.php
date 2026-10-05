<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Reports a relation whose persistence does not suit the schema written on its name.
 *
 * Rule: PG-CREATION-SCHEMA-001. The server adjusts the persistence of a new
 * table, view, sequence or materialized view to its schema
 * (RangeVarAdjustRelationPersistence, namespace.c): a temporary relation
 * written in a schema that is not a temporary schema is an error ("cannot
 * create temporary relation in non-temporary schema"); an unlogged relation
 * in `pg_temp`, the session's temporary schema, is an error ("only temporary
 * relations may be created in temporary schemas"); a permanent relation in
 * `pg_temp` becomes temporary. Schemas named `pg_temp_N` or
 * `pg_toast_temp_N` are temporary schemas whose owner session the statement
 * does not tell, so they are not reported. The schema itself is assumed to
 * exist; the server reports a missing schema first.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/catalog/namespace.c. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CreationSchemas
{
    /**
     * Reports the persistence problem of a relation created under a written name.
     */
    public function check(Derivation $derivation, QualifiedName $written, Persistence $persistence): void
    {
        $schema = $written->schema?->value;
        if ($schema === null) {
            return;
        }
        $temporary = str_starts_with($schema, 'pg_temp') || str_starts_with($schema, 'pg_toast_temp');
        if ($persistence->temporary() && !$temporary) {
            $derivation->report(new DefinitionProblem(DefinitionRule::TemporaryInPermanentSchema));
        }
        if ($persistence === Persistence::Unlogged && $schema === 'pg_temp') {
            $derivation->report(new DefinitionProblem(DefinitionRule::UnloggedInTemporarySchema));
        }
    }
}
