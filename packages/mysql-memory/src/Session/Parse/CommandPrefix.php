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
 * MySQL 5.6/5.7 require a default database after an unqualified CREATE TABLE or LOAD target and
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
        if (in_array($command, ['DESC', 'DESCRIBE', 'EXPLAIN'], true)) {
            return $this->format($statement, $end, $session);
        }
        if (!$this->applicable($tokens, $session)) {
            return null;
        }
        $semantics = $session->semantics();
        foreach ($tokens as $index => $token) {
            if ($token->end() > $end) {
                break;
            }
            $next = strtoupper($tokens[$index + 1]->text ?? '');
            if ($token->name === '@' || in_array($next, $command === 'ALTER' ? ['.'] : ['.', 'PARTITION'], true)) {
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
            } catch (\SqlSemantics\Construction\RejectedInput $failure) {
                return $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTemplate
                    ? $this->completed($failure->problem->definition, $tokens[$index + 1] ?? null, $statement, $session)
                    : null;
            } catch (AnalysisException $failure) {
                return (new Syntax())->error($failure, $statement, $session->settings()->release(), $session->following);
            }
            $earlier = $this->completed($operation->statement, $tokens[$index + 1] ?? null, $statement, $session);
            if ($earlier !== null) {
                return $earlier;
            }
        }

        return null;
    }

    /**
     * Resolves MySQL 5.7 variables after a complete statement prefix, before a
     * trailing unexpected token. An unfinished FROM, UNION or variable list
     * does not reach this binding stage.
     */
    public function bindings(string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            return null;
        }
        $semantics = $session->semantics();
        try {
            $tree = $semantics->parser()->parse(substr($statement, 0, $end));
            $operation = $semantics->analyze($tree, $semantics->context([], session: $session->resolution()));
        } catch (SourceException|AnalysisException|\SqlSemantics\Construction\RejectedInput) {
            return null;
        }
        foreach ($operation->facts->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable) {
                return (new RoutinePrefix())->database($statement, $end, $session)
                    ?? (new \MySqlMemory\Session\CacheOptions())->undeclared((new \MySqlMemory\Session\Problem\Errors())->error($problem, $session), $statement, $session);
            }
        }
        return null;
    }

    /**
     * Selects commands whose completed prefixes can reject later malformed input.
     *
     * @param list<Token> $tokens The input statement tokens
     */
    public function applicable(array $tokens, Session $session): bool
    {
        $command = strtoupper($tokens[0]->text ?? '');

        if ($session->variables->database !== '' && $this->tableCommand($tokens)) {
            return false;
        }

        return ($command === 'ALTER' && in_array(strtoupper($tokens[1]->text ?? ''), ['DATABASE', 'SCHEMA'], true))
            || ($session->settings()->legacy() && (in_array($command, ['LOAD', 'CREATE', 'REVOKE', 'SET'], true) || $command === 'ALTER' && in_array(strtoupper($tokens[1]->text ?? ''), ['TABLE', 'IGNORE'], true)));
    }

    /**
     * Identifies table prefixes whose only early requirement is a selected database.
     *
     * @param list<Token> $tokens The input statement tokens
     */
    public function tableCommand(array $tokens): bool
    {
        $command = strtoupper($tokens[0]->text ?? '');
        return in_array($command, ['LOAD', 'CREATE'], true) || $command === 'ALTER' && in_array(strtoupper($tokens[1]->text ?? ''), ['TABLE', 'IGNORE'], true);
    }

    /**
     * Interprets completed format notices before the legacy statement's refusal.
     */
    public function format(string $statement, int $end, Session $session): ?SqlError
    {
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        foreach ((new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $statement, $end, $semantics->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client) as [$offset, $notice]) {
            if ($notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($notice->problem, $session);
            }
        }

        return null;
    }

    /**
     * Answers the condition raised after a command prefix has been resolved.
     */
    public function completed(\SqlSemantics\Statement\Statement|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $model, ?Token $next, string $statement, Session $session): ?SqlError
    {
        if (($model instanceof LoadTable && $model->table->name->schema === null) || (($model instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable || $model instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable) && $model->name->schema === null) || ($model instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable && $model->table->schema === null)) {
            return $session->variables->database === '' ? QueryError::NoDatabase->error() : null;
        }
        if ($model instanceof \SqlSemantics\Platform\MySql\Statement\Account\SetPassword && $next?->text === ',' && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            return (new Syntax())->near($next->end(), $statement, $session->following, new \SqlParser\Parser\SyntaxException($next, [], ''));
        }

        return null;
    }
}
