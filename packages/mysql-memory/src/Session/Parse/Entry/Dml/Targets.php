<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Dml;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget;

/**
 * Resolves DELETE target namespaces from complete statements and unfinished prefixes.
 *
 * @visibility MySqlMemory
 */
final class Targets
{
    /**
     * Checks the complete deletion's target list using already published bindings.
     * A target absent from every input under every name comparison is a construction
     * invariant of the statement, so only the repeated and database conditions remain here.
     *
     * @throws SqlError When a written target is repeated or needs a database
     */
    public function statement(\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete $write, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session): void
    {
        $this->names($write->targets, $session);
    }

    /**
     * Registers written target names before input charset and alias resolution.
     *
     * @param non-empty-list<\SqlSemantics\Statement\Identifier\QualifiedName> $targets Ordered target names
     * @throws SqlError When an early database or duplicate-name condition fails
     */
    public function names(array $targets, Session $session): void
    {
        $database = $session->variables->database;
        $seen = [];
        foreach ($targets as $target) {
            if ($target->schema === null && $database === '' && ((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetNames())->duplicate($targets) !== null || $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651)) {
                throw QueryError::NoDatabase->error();
            }
            $key = serialize([$target->schema->value ?? $database, $target->name->value]);
            if (isset($seen[$key])) {
                throw QueryError::NonUniqueTable->error($target->name->value);
            }
            $seen[$key] = true;
        }
    }

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
