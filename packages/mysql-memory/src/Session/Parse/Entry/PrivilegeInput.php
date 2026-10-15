<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\Account\SyntaxPrivileges;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;

/**
 * Resolves the connection database required by a completed privilege target.
 *
 * Target interpretation belongs to SQL Semantics. Input is retained only by
 * this boundary to collect conditions preceding the target's completion.
 *
 * @visibility MySqlMemory
 */
final class PrivilegeInput
{
    /**
     * Reports a missing database before recipient-name and privilege-list failures.
     */
    public function database(string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->variables->database !== '' || $session->settings()->legacy()) {
            return null;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $boundary = (new SyntaxPrivileges())->database($parser, $statement, $end, $semantics->profile());
        if ($boundary === null) {
            return null;
        }
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $boundary, $semantics->profile());

        return (new ModernWarnings())->recorded(array_column($notices, 1), QueryError::NoDatabase->error(), $session);
    }
}
