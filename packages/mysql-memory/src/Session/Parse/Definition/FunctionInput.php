<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\Call\SyntaxCalls;

/**
 * Resolves implicit routine databases at completed legacy function calls.
 *
 * MySQL 5.6 reports an absent function without a default database, and 5.7
 * reports the missing database. Both conditions precede a later syntax error.
 * Only typed routine names leave semantic lowering.
 *
 * @visibility MySqlMemory
 */
final class FunctionInput
{
    /**
     * Answers an unresolved implicit function database before a later refusal.
     */
    public function before(string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->variables->database !== '' || !$session->settings()->legacy()) {
            return null;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        foreach ((new SyntaxCalls())->dependencies($parser, $statement, $end, $semantics->profile()) as [$offset, $name]) {
            return $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? ProgramError::RoutineMissing->error('FUNCTION', $name->value) : QueryError::NoDatabase->error();
        }
        return null;
    }
}
