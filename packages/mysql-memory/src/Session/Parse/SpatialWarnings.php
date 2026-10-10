<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

/**
 * Retains legacy spatial-name warnings for complete calls before a later syntax error.
 *
 * Each candidate is parsed and resolved through the normal pipeline. An unfinished call,
 * invalid arity or qualified stored-function name cannot contribute a native deprecation.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/spatial-function-reference.html.
 *
 * @visibility MySqlMemory
 */
final class SpatialWarnings
{
    /**
     * Answers completed-call deprecations by their closing-parenthesis offset.
     *
     * @param list<Token> $tokens The tokens of the refused statement
     * @return array<int, SqlError>
     */
    public function before(array $tokens, string $statement, int $end, Session $session): array
    {
        $stack = [];
        $warnings = [];
        foreach ($tokens as $index => $token) {
            if ($token->offset >= $end) {
                break;
            }
            if ($token->text === '(') {
                $stack[] = $index;
            } elseif ($token->text === ')' && $stack !== []) {
                $open = array_pop($stack);
                $name = $tokens[$open - 1] ?? null;
                if ($name === null || ($tokens[$open - 2]->text ?? '') === '.') {
                    continue;
                }
                $warning = Deprecation::spatialWarning(trim($name->text, '`'), $session->settings()->release());
                if ($warning !== null && $this->complete(substr($statement, $name->offset, $token->offset + 1 - $name->offset), $session)) {
                    $warnings[$token->offset] = new SqlError(StatementError::DeprecatedSyntax, $warning->message());
                }
            }
        }

        return $warnings;
    }

    /**
     * Tells whether the candidate resolves as a native one-argument call.
     */
    public function complete(string $expression, Session $session): bool
    {
        try {
            $semantics = $session->semantics();
            $operation = $semantics->analyze($semantics->parser()->parse('SELECT ' . $expression));
        } catch (SourceException|AnalysisException) {
            return false;
        }
        $item = $operation->statement instanceof Select ? ($operation->statement->items[0] ?? null) : null;
        $call = $item instanceof SelectExpression ? $item->expression : null;

        return $call instanceof FunctionCall && $call->schema === null && count($call->arguments) === 1 && !$call->named();
    }
}
