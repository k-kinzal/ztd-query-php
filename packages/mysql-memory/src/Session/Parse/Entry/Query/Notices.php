<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Query;

use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection;

/**
 * Retains completed query-tail notices when a fixed query constraint refuses publication.
 *
 * The query and its destination come from semantic values. Earlier input conditions
 * keep their existing positions and can stop before these completion notices.
 *
 * @visibility MySqlMemory
 */
final class Notices
{
    /**
     * @return list<array{int, Deprecation}> Completion notices before the retained preparation failure
     */
    public function completed(RejectedInput $failure, Session $session): array
    {
        $problem = $failure->problem;
        $query = match (true) {
            $problem instanceof RefusedQuery => $problem->query,
            $problem instanceof RefusedSelection => $problem->selection,
            default => null,
        };
        if ($query === null) {
            return [];
        }
        $query = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($query);
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $query->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $notices = [];
        foreach ((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->warnings as $warning) {
            if ($warning instanceof Deprecation && Stages::afterReading($warning)) {
                $notices[] = [$failure->end, $warning];
            }
        }
        return $notices;
    }
}
