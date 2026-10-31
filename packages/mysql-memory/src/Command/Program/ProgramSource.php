<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The text of a statement that creates a stored program, and the parts of it the server keeps as they were written.
 *
 * The server keeps the parameter list of a routine between its parentheses and the body from
 * its first token to the end of the statement, comments and a closing semicolon included and
 * trailing white space left out (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-procedure.html.
 *
 * @visibility MySqlMemory
 */
final class ProgramSource
{
    /** @var array<string, string> Original display bodies by their input production */
    private readonly array $bodies;

    /** @var array<string, string> Parenthesized parameter text by its input production */
    private readonly array $parameters;

    private readonly string $triggerHeader;

    /**
     * Extracts display strings at the input boundary without retaining the parser tree.
     *
     * @param string $text The statement text
     * @param Node $tree The tree already parsed by the input reader
     */
    public function __construct(string $text, Node $tree)
    {
        $bodies = [];
        foreach (['stored_routine_body', 'sp_proc_stmt', 'ev_sql_stmt'] as $production) {
            $span = ($tree->find($production)[0] ?? null)?->span();
            if ($span !== null) {
                $bodies[$production] = rtrim(substr($text, $span[0]));
            }
        }
        $parameters = [];
        foreach (['sp_tail', 'sf_tail'] as $production) {
            $node = $tree->find($production)[0] ?? null;
            $open = null;
            foreach ($node->children ?? [] as $child) {
                if ($child instanceof Token && $child->text === '(' && $open === null) {
                    $open = $child->end();
                }
                if ($child instanceof Token && $child->text === ')' && $open !== null) {
                    $parameters[$production] = substr($text, $open, $child->offset - $open);
                    break;
                }
            }
        }
        $this->bodies = $bodies;
        $this->parameters = $parameters;
        $start = ($tree->find('trigger_tail')[0] ?? null)?->span()[0] ?? null;
        $order = ($tree->find('trigger_follows_precedes_clause')[0] ?? null)?->span();
        $this->triggerHeader = $start === null ? '' : ($order === null ? rtrim(substr($text, $start)) : rtrim(substr($text, $start, $order[0] - $start)) . ' ' . trim(substr($text, $order[1])));
    }

    /**
     * Answers the text from the first node of a name to the end of the statement, without trailing white space.
     */
    public function body(string $node): string
    {
        return $this->bodies[$node] ?? '';
    }

    /**
     * Returns the legacy trigger header with its ordering clause omitted.
     */
    public function trigger(): string
    {
        return $this->triggerHeader;
    }

    /**
     * Answers the captured text between the parameter-list parentheses.
     */
    public function between(string $node): string
    {
        return $this->parameters[$node] ?? '';
    }

    /**
     * Answers the user and host of a DEFINER clause; without one, or with CURRENT_USER, the account of the session.
     *
     * An account that does not exist is a note (ER_NO_SUCH_USER); the program is created all the
     * same.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-objects-security.html.
     *
     * @return array{string, string}
     */
    public static function definer(?Account $definer, Session $session, ?\MySqlMemory\Evaluation\Context $context = null, ?\MySqlMemory\Session\Diagnostics $diagnostics = null): array
    {
        $diagnostics ??= $context?->diagnostics;
        if ($definer instanceof AccountName) {
            $identity = \MySqlMemory\Account\Identity::of($definer->username(), $definer->hostname());
            $host = $identity->host;
            if ($diagnostics !== null && $session->instance->accounts->find($identity) === null) {
                $diagnostics->note(AccountError::NoSuchUser, $identity->missingDefiner($session->settings()->legacy()));
            }

            return [$identity->user, $host];
        }
        $account = $session->variables->definer;
        return [$account->user, $account->host];
    }

    /**
     * Tells whether the value of a boolean system variable is on.
     */
    public static function enabled(string|int|null $value): bool
    {
        return in_array(strtoupper((string) $value), ['ON', '1'], true);
    }

    /**
     * Answers the database a program name is in: the one it names, else the current one.
     *
     * @throws \MySqlMemory\Error\SqlError When the name names none and no database is selected (ER_NO_DB_ERROR)
     */
    public static function database(?Name $schema, Session $session): string
    {
        $database = $schema->value ?? $session->variables->database;
        if ($database === '') {
            throw QueryError::NoDatabase->error();
        }

        return $database;
    }

    /**
     * Answers the character_set_client, collation_connection and database collation of a program created in a session.
     *
     * @return array{string, string, string}
     */
    public static function charsets(Session $session, string $schema): array
    {
        return [(string) $session->variables->read('character_set_client'), (string) $session->variables->read('collation_connection'), $session->instance->dictionary->schema($schema)->collation ?? 'utf8mb4_0900_ai_ci'];
    }

    /**
     * Answers the current time as the dictionary records it, to the second or to a number of fractional digits.
     */
    public static function now(int $digits = 0): string
    {
        $now = microtime(true);
        $text = date('Y-m-d H:i:s', (int) $now);

        return $digits === 0 ? $text : $text . '.' . substr(sprintf('%06d', (int) (($now - floor($now)) * 1000000)), 0, $digits);
    }
}
