<?php

declare(strict_types=1);

namespace MySqlMemory\System\Program;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;

/**
 * The rows of INFORMATION_SCHEMA.PARAMETERS: the value each stored function returns, at position 0, and the parameters of each routine, by routine and position.
 *
 * A parameter of a function is IN; the value a function returns has no mode and no name
 * (verified on a live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-parameters-table.html.
 *
 * @visibility MySqlMemory
 */
final class Parameters implements SystemRows
{
    /**
     * Answers a row for each parameter and returned value.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Typed::routines($reading) as $routine) {
            $statement = $routine->statement;
            $named = ['SPECIFIC_CATALOG' => 'def', 'SPECIFIC_SCHEMA' => $routine->schema, 'SPECIFIC_NAME' => $routine->name];
            if ($statement instanceof CreateFunction) {
                $rows[] = $named + ['ORDINAL_POSITION' => 0, 'PARAMETER_MODE' => null, 'PARAMETER_NAME' => null] + Typed::columns($routine, $statement->returns, $statement->collation?->name?->value, $reading) + ['ROUTINE_TYPE' => 'FUNCTION'];
            }
            foreach ($statement->parameters->parameters as $position => $parameter) {
                $rows[] = $named + [
                    'ORDINAL_POSITION' => $position + 1,
                    'PARAMETER_MODE' => $parameter->mode->value ?? 'IN',
                    'PARAMETER_NAME' => $parameter->name->value,
                ] + Typed::columns($routine, $parameter->type, $parameter->collation?->name?->value, $reading) + ['ROUTINE_TYPE' => $routine->kind()];
            }
        }

        return $rows;
    }
}
