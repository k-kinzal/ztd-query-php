<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Operation;

/**
 * Formats semantic diagnostics that quote their occurrence in the original parser input.
 *
 * Source ranges belong to the analyzed operation; equal expressions elsewhere in a statement
 * cannot supply the location. A directly constructed operation has no original input location.
 *
 * @visibility MySqlMemory
 */
final class LocatedErrors
{
    /**
     * Answers the original SQL tail and line of a forbidden partition expression, or null for another diagnostic.
     */
    public function error(Diagnostic $problem, Operation $operation, Session $session): ?SqlError
    {
        if (!$problem instanceof InvalidPartitionExpression) {
            return null;
        }
        $origin = $operation->sources->of($problem->expression);
        if ($origin === null) {
            return new SqlError(StatementError::ParseError, $problem->message());
        }
        $offset = $origin->offset;
        if (in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $offset += $origin->length;
            foreach ($session->semantics()->parser()->tokenize($session->text) as $token) {
                if ($token->offset >= $offset && $token->text !== '') {
                    $offset = $token->offset;
                    break;
                }
            }
        }
        $tail = mb_strcut(rtrim(substr($session->text, $offset) . $session->following), 0, 80, 'UTF-8');
        $line = substr_count(ltrim(substr($session->text, 0, $offset)), "\n") + 1;

        return new SqlError(StatementError::ParseError, $problem->message() . " near '" . $tail . "' at line " . $line);
    }
}
