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
        if (in_array($command, ['LOAD', 'CREATE'], true) && $session->variables->database !== '') {
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
            } catch (\SqlSemantics\Construction\RejectedInput) {
                return null;
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
     * Selects commands whose completed prefixes can reject later malformed input.
     *
     * @param list<Token> $tokens The input statement tokens
     */
    public function applicable(array $tokens, Session $session): bool
    {
        $command = strtoupper($tokens[0]->text ?? '');

        return ($command === 'ALTER' && in_array(strtoupper($tokens[1]->text ?? ''), ['DATABASE', 'SCHEMA'], true))
            || ($session->settings()->legacy() && in_array($command, ['LOAD', 'CREATE', 'REVOKE', 'SET'], true));
    }

    /**
     * Interprets completed format notices before the legacy statement's refusal.
     */
    public function format(string $statement, int $end, Session $session): ?SqlError
    {
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        foreach ((new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $statement, $end, $semantics->profile()) as [$offset, $notice]) {
            if ($notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($notice->problem, $session);
            }
        }

        return null;
    }

    /**
     * Answers the condition raised after a command prefix has been resolved.
     */
    public function completed(\SqlSemantics\Statement\Statement $model, ?Token $next, string $statement, Session $session): ?SqlError
    {
        if (($model instanceof LoadTable && $model->table->name->schema === null) || ($model instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable && $model->name->schema === null)) {
            return $session->variables->database === '' ? QueryError::NoDatabase->error() : null;
        }
        if ($model instanceof \SqlSemantics\Platform\MySql\Statement\Account\SetPassword && $next?->text === ',' && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            return (new Syntax())->near($next->end(), $statement, $session->following, new \SqlParser\Parser\SyntaxException($next, [], ''));
        }

        return null;
    }
}
