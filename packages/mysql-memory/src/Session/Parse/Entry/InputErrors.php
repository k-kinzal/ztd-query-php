<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Dispatches typed input refusals whose messages need the original syntax excerpt.
 *
 * SQL text is confined to input formatting and is never used to execute a structure.
 *
 * @visibility MySqlMemory
 */
final class InputErrors
{
    /**
     * Formats an input refusal, or leaves other diagnostics to their semantic family.
     */
    public function error(Diagnostic $diagnostic, Session $session): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof RefusedSourceNumber => (new SourceNumberInput())->error($diagnostic, $session->text),
            $diagnostic instanceof RoleOrPrivilegeMismatch => (new RoleInput())->error($diagnostic, $session->text),
            default => null,
        };
    }
}
