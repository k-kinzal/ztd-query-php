<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;

/**
 * Resolves an assignment target before a later MySQL 5.6 syntax error.
 *
 * A prefix ending at the target is parsed and resolved with a DEFAULT value.
 * It never executes an assignment. Incomplete targets retain the original
 * syntax diagnostic. Observed through SET ROLE lexical-boundary cases on 5.6.51.
 *
 * @visibility MySqlMemory
 */
final class VariablePrefix
{
    /**
     * Answers an unknown assignment target that precedes the syntax error.
     *
     * @param list<Token> $tokens The original statement tokens
     */
    public function before(array $tokens, string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->settings()->release() !== GrammarRelease::MySql5651 || strtoupper($tokens[0]->text ?? '') !== 'SET') {
            return null;
        }
        try {
            $semantics = $session->semantics();
            $tree = $semantics->parser()->parse(substr($statement, 0, $end) . '=DEFAULT');
            $operation = $semantics->analyze($tree, $semantics->context([], session: $session->resolution()));
        } catch (SourceException|AnalysisException) {
            return null;
        }
        foreach ($operation->facts->diagnostics as $problem) {
            if ($problem instanceof UnknownSystemVariable && $problem->assigned) {
                return AdministrationError::UnknownSystemVariable->error($problem->name);
            }
        }

        return null;
    }
}
