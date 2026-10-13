<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;

/**
 * Preserves errors the legacy parser raises before reading later command clauses.
 *
 * MySQL 5.6/5.7 require a default database after an unqualified LOAD target and
 * its optional partition list. MySQL 5.6 rejects authentication in REVOKE while
 * reading the account. Completed prefixes are parsed and resolved without
 * executing the commands. Observed on isolated 5.6.51 and 5.7.44 servers.
 *
 * @visibility MySqlMemory
 */
final class CommandPrefix
{
    /**
     * Answers a command error found before the reported syntax error, if any.
     *
     * @param list<Token> $tokens The original statement tokens
     */
    public function before(array $tokens, string $statement, int $end, Session $session): ?SqlError
    {
        $command = strtoupper($tokens[0]->text ?? '');
        if (!$session->settings()->legacy() || !in_array($command, ['LOAD', 'REVOKE'], true)) {
            return null;
        }
        if ($command === 'LOAD' && $session->variables->database !== '') {
            return null;
        }
        $semantics = $session->semantics();
        foreach ($tokens as $index => $token) {
            if ($token->end() > $end) {
                break;
            }
            if (in_array(strtoupper($tokens[$index + 1]->text ?? ''), ['.', 'PARTITION'], true)) {
                continue;
            }
            if ($command === 'REVOKE' && in_array(strtoupper($tokens[$index + 1]->text ?? ''), ['AS', 'BY'], true)) {
                continue;
            }
            try {
                $tree = $semantics->parser()->parse(substr($statement, 0, $token->end()));
            } catch (SourceException) {
                continue;
            }
            try {
                $operation = $semantics->analyze($tree, $semantics->context([], session: $session->resolution()));
            } catch (AnalysisException $failure) {
                return (new Syntax())->error($failure, $statement, $session->settings()->release(), $session->following);
            }
            $model = $operation->statement;
            if ($model instanceof LoadTable && $model->table->name->schema === null) {
                return QueryError::NoDatabase->error();
            }
        }

        return null;
    }
}
