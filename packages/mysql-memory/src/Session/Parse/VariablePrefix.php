<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;

/**
 * Resolves an assignment target before a later MySQL 5.6 syntax error.
 *
 * Prefixes ending at assignment operators or the syntax error are parsed and
 * resolved with a DEFAULT value, in source order.
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
        $positions = [];
        foreach ($tokens as $token) {
            if ($token->offset <= $end && in_array($token->text, ['=', ':='], true)) {
                $positions[] = $token->offset;
            }
        }
        $positions[] = $end;
        $semantics = $session->semantics();
        foreach (array_unique($positions) as $position) {
            try {
                $tree = $semantics->parser()->parse(substr($statement, 0, $position) . '=DEFAULT');
                $operation = $semantics->analyze($tree, $semantics->context([], session: $session->resolution()));
            } catch (SourceException|AnalysisException) {
                continue;
            }
            foreach ($operation->facts->diagnostics as $problem) {
                if ($problem instanceof UnknownSystemVariable) {
                    return (new Errors())->error($problem, $session);
                }
            }
        }

        return null;
    }
}
