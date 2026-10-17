<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Dml;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget;

/**
 * Resolves the ordered database dependencies of a refused DELETE target prefix.
 *
 * @visibility MySqlMemory
 */
final class Targets
{
    /**
     * Formats the first condition from typed names without reading source SQL.
     *
     * @throws LogicException When the retained prefix violates its construction invariant
     */
    public function error(RepeatedDeleteTarget $problem, Session $session): SqlError
    {
        $database = $problem->program->schema->value ?? $session->variables->database;
        if ($problem->program !== null && $database === '') {
            return QueryError::NoDatabase->error();
        }
        $seen = [];
        foreach ($problem->targets as $target) {
            if ($target->schema === null && $database === '') {
                return QueryError::NoDatabase->error();
            }
            $key = serialize([$target->schema->value ?? $database, $target->name->value]);
            if (isset($seen[$key])) {
                return QueryError::NonUniqueTable->error($target->name->value);
            }
            $seen[$key] = true;
        }

        throw new LogicException('A refused DELETE prefix must contain a repeated target.');
    }
}
