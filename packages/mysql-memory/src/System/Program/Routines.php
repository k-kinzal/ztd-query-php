<?php

declare(strict_types=1);

namespace MySqlMemory\System\Program;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;

/**
 * The rows of INFORMATION_SCHEMA.ROUTINES: one for each stored procedure and function, by database and name.
 *
 * A function describes the type it returns as PARAMETERS does; a procedure has an empty
 * DATA_TYPE and no other type column. The definition is the body as it was written (verified on
 * live 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-routines-table.html.
 *
 * @visibility MySqlMemory
 */
final class Routines implements SystemRows
{
    /**
     * Answers a row for each routine.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Typed::routines($reading) as $routine) {
            $statement = $routine->statement;
            $typed = $statement instanceof CreateFunction ? Typed::columns($routine, $statement->returns, $statement->collation?->name?->value, $reading) : ($routine->installed->metadata ?? ['DATA_TYPE' => '', 'DTD_IDENTIFIER' => null]);
            unset($typed['SQL_DATA_ACCESS'], $typed['SECURITY_TYPE'], $typed['ROUTINE_COMMENT']);
            $rows[] = [
                'SPECIFIC_NAME' => $routine->name,
                'ROUTINE_CATALOG' => 'def',
                'ROUTINE_SCHEMA' => $routine->schema,
                'ROUTINE_NAME' => $routine->name,
                'ROUTINE_TYPE' => $routine->kind(),
            ] + $typed + [
                'ROUTINE_BODY' => 'SQL',
                'ROUTINE_DEFINITION' => $routine->installed === null ? $routine->body : null,
                'EXTERNAL_NAME' => null,
                'EXTERNAL_LANGUAGE' => $reading->dictionary() ? 'SQL' : null,
                'PARAMETER_STYLE' => 'SQL',
                'IS_DETERMINISTIC' => $routine->deterministic ? 'YES' : 'NO',
                'SQL_DATA_ACCESS' => $routine->access,
                'SQL_PATH' => null,
                'SECURITY_TYPE' => $routine->security,
                'CREATED' => $routine->created,
                'LAST_ALTERED' => $routine->modified,
                'SQL_MODE' => $routine->mode,
                'ROUTINE_COMMENT' => $routine->comment,
                'DEFINER' => $routine->definer[0] . '@' . $routine->definer[1],
                'CHARACTER_SET_CLIENT' => $routine->charsets[0],
                'COLLATION_CONNECTION' => $routine->charsets[1],
                'DATABASE_COLLATION' => $routine->charsets[2],
            ];
        }

        return $rows;
    }
}
