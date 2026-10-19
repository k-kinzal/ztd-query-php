<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Load;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Preserves LOAD input name resolution before a refused option combination.
 *
 * Only typed operands are inspected. Unknown character sets precede database
 * selection; assignment preparation precedes the algorithm conflict, while
 * opening tables and files follows it. Observed on MySQL 8.2, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class Options
{
    /**
     * Formats the first state or option condition without reconstructing a statement.
     */
    public function error(RefusedLoadOptions $problem, Session $session): SqlError
    {
        $charset = $problem->charset?->name;
        if ($charset !== null && Charset::named($charset->value) === null) {
            return (new Errors())->error(new UnknownCharset($charset->value), $session);
        }
        if ($problem->table->schema === null && $session->variables->database === '') {
            return QueryError::NoDatabase->error();
        }
        $derivation = new Derivation($session->semantics()->context(session: $session->resolution()));
        foreach ($problem->assignments as $assignment) {
            if (!$assignment->value instanceof DefaultRequest) {
                $derivation->scalar($assignment->value, $derivation->environment());
            }
        }
        foreach ($derivation->facts()->diagnostics as $diagnostic) {
            if (Stages::parsed($diagnostic)) {
                return (new Errors())->error($diagnostic, $session);
            }
        }
        return (new Errors())->error($problem->problem, $session);
    }
}
