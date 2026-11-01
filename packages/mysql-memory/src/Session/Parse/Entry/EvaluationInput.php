<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;

/**
 * Formats a DO wildcard refusal and its earlier default-database dependency.
 *
 * @visibility MySqlMemory
 */
final class EvaluationInput
{
    /**
     * Resolves DO's completed database dependencies before a later input refusal.
     * Parser objects stay at the input boundary; execution receives the complete
     * semantic instruction instead. A routine dependency emits two conditions
     * in modern releases, whereas a table dependency emits one.
     */
    public function before(string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->variables->database !== '') {
            return null;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $tokens = (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($parser, $statement);
        if (($tokens[0]->name ?? '') !== 'DO_SYM') {
            return null;
        }
        $conditions = [];
        foreach ((new \SqlSemantics\Platform\MySql\Lowering\Call\SyntaxCalls())->dependencies($parser, $statement, $end, $semantics->profile()) as [$offset, $name]) {
            $conditions[$offset] = (new \MySqlMemory\Session\Problem\Errors())->routine(new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall($name), $session);
        }
        try {
            $parser->parse($statement, static function (\SqlParser\Parser\Node $node) use (&$conditions, $end): void {
                foreach ((new DerivedInput())->conditions($node, $end, '') as [$offset, $error]) {
                    $conditions[$offset] ??= $error;
                }
            });
        } catch (\SqlParser\Lexer\SourceException) {
            // Only completed dependencies precede the original refusal.
        }
        ksort($conditions);
        $offset = array_key_first($conditions);
        return $offset === null ? null : (new \MySqlMemory\Session\CacheOptions())->literal($conditions[$offset], $offset, $statement, $session);
    }

    /**
     * Answers the native refusal without constructing or executing a replacement statement.
     */
    public function error(EvaluationWildcard $problem, Session $session): SqlError
    {
        if ($problem->requiresDatabase && $session->variables->database === '') {
            $error = QueryError::NoDatabase->error();
            return new SqlError($error->error, $error->getMessage(), following: $session->settings()->legacy() ? [] : [[$error->getCode(), $error->getMessage()]]);
        }
        $table = $problem->table;

        return $table === null ? QueryError::NoTablesUsed->error() : SchemaError::BadTable->error(($table->schema === null ? '' : $table->schema->value . '.') . $table->name->value);
    }
}
