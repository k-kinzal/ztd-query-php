<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;

/**
 * Checks the connection database at a completed definition name before a refused body.
 *
 * The parser identifies the definition-name operand and its qualification. This
 * state-dependent refusal precedes body operands on MySQL 5.6 through 9.1.
 * No tree or input text is passed to statement execution.
 *
 * @visibility MySqlMemory
 */
final class DefinitionPrefix
{
    /**
     * Answers a missing default database before a rejected operand of a table or view definition.
     */
    public function database(string $statement, int $end, Session $session, string $keyword = 'VIEW_SYM'): ?SqlError
    {
        if ($session->variables->database !== '') {
            return null;
        }
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $tokens = (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($parser, $statement);
        $statement = substr($statement, 0, $tokens[count($tokens) - 1]->offset);
        if (!in_array(strtoupper($tokens[0]->text ?? ''), ['CREATE', 'ALTER'], true)) {
            return null;
        }
        $start = null;
        foreach ($tokens as $index => $token) {
            if ($token->name === $keyword) {
                $start = $tokens[$index + 1]->offset ?? null;
                break;
            }
            if (in_array($token->name, ['SELECT_SYM', 'PROCEDURE_SYM', 'FUNCTION_SYM', 'TRIGGER_SYM', 'EVENT_SYM'], true)) {
                return null;
            }
        }
        if ($start === null) {
            return null;
        }
        $missing = false;
        $seen = false;
        try {
            $parser->parse($statement, static function (Node $node) use (&$missing, &$seen, $end, $start): void {
                if ($seen || $node->name !== 'table_ident' || ($node->tokens()[0]->offset ?? null) !== $start) {
                    return;
                }
                $seen = true;
                $tokens = $node->tokens();
                $last = $tokens[count($tokens) - 1] ?? null;
                $missing = $missing || ($last !== null && $last->end() < $end && count($tokens) < 3);
            });
        } catch (SourceException) {
            // Only definition names completed before the original refusal are relevant.
        }

        return $missing ? QueryError::NoDatabase->error() : null;
    }
}
