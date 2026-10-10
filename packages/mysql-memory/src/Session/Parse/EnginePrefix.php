<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;

/**
 * Recovers completed table options before a later syntax error in a legacy statement.
 *
 * MySQL 5.6 and 5.7 resolve an engine name while parsing it. An unknown ENGINE before
 * an unsupported tail therefore warns or fails before the syntax error. A prefix that
 * itself parses as a complete table statement is parsed and resolved normally; no table
 * command is executed. Incomplete prefixes are left to the original syntax diagnostic.
 * Verified with CREATE TABLE ... ENGINE=bad START TRANSACTION on both releases.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sqlmode_no_engine_substitution.
 *
 * @visibility MySqlMemory
 */
final class EnginePrefix
{
    /**
     * Answers the unknown engine conditions in a complete prefix, by source offset.
     *
     * @param list<Token> $tokens The original statement tokens
     * @return array<int, SqlError>
     */
    public function before(array $tokens, string $statement, int $end, Session $session): array
    {
        if (!in_array(strtoupper($tokens[0]->text ?? ''), ['CREATE', 'ALTER'], true)) {
            return [];
        }
        try {
            $semantics = $session->semantics();
            $tree = $semantics->parser()->parse(substr($statement, 0, $end));
            $operation = $semantics->analyze($tree);
        } catch (SourceException|AnalysisException) {
            return [];
        }
        if (!$operation->statement instanceof CreateTable && !$operation->statement instanceof AlterTable) {
            return [];
        }
        $errors = [];
        foreach ((new Walker())->find($operation->statement, EngineOption::class) as $option) {
            $source = $operation->sources->of($option);
            if ($source !== null && ServerCatalog::shared()->engine($option->engine->value) === null) {
                $errors[$source->offset] = SchemaError::UnknownStorageEngine->error($option->engine->value);
            }
        }
        ksort($errors);

        return $errors;
    }
    /**
     * Separates recoverable engine warnings from the first engine error that ends parsing.
     *
     * @param list<Token> $tokens The original statement tokens
     * @return array{int, array<int, SqlError>, SqlError} Parsing boundary, warnings and final error
     */
    public function conditions(array $tokens, string $statement, int $end, Session $session, SqlError $fallback): array
    {
        $engines = $this->before($tokens, $statement, $end, $session);
        $first = array_key_first($engines);
        if ($first === null || !$session->modes()->has('NO_ENGINE_SUBSTITUTION')) {
            return [$end, $engines, $fallback];
        }

        return [min($end, $first), [], $engines[$first]];
    }

}
