<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Error\ErrorCode;
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
    /**
     * @param string $text The statement text
     * @param Node $tree The parse tree of the text
     */
    public function __construct(public readonly string $text, public readonly Node $tree)
    {
    }

    /**
     * Answers the source of the statement a session executes.
     *
     * @throws \MySqlMemory\Error\SqlError When the text does not parse
     */
    public static function of(Session $session): self
    {
        try {
            return new self($session->text, $session->semantics()->parser()->parse($session->text));
        } catch (\SqlParser\Lexer\SourceException $error) {
            throw (new \MySqlMemory\Session\Syntax())->error($error, $session->text);
        }
    }

    /**
     * Answers the text from the first node of a name to the end of the statement, without trailing white space.
     */
    public function body(string $node): string
    {
        $found = $this->tree->find($node);
        $span = $found === [] ? null : $found[0]->span();

        return $span === null ? '' : rtrim(substr($this->text, $span[0]));
    }

    /**
     * Answers the text of the first node of a name, as it was written.
     */
    public function text(string $node): string
    {
        $found = $this->tree->find($node);

        return $found === [] ? '' : $found[0]->text($this->text);
    }

    /**
     * Answers the text between the parentheses that are tokens of the first node of a name.
     */
    public function between(string $node): string
    {
        $found = $this->tree->find($node);
        $open = null;
        foreach ($found === [] ? [] : $found[0]->children as $child) {
            if ($child instanceof Token && $child->text === '(' && $open === null) {
                $open = $child->end();
            }
            if ($child instanceof Token && $child->text === ')' && $open !== null) {
                return substr($this->text, $open, $child->offset - $open);
            }
        }

        return '';
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
    public static function definer(?Account $definer, Session $session, ?\MySqlMemory\Evaluation\Context $context = null): array
    {
        if ($definer instanceof AccountName) {
            $host = $definer->host->value ?? '%';
            if ($context !== null && $session->instance->accounts->find(new \MySqlMemory\Account\Identity($definer->user->value, $host)) === null) {
                $context->note(ErrorCode::NoSuchUser, $definer->user->value, $host);
            }

            return [$definer->user->value, $host];
        }
        $account = $session->variables->definer;
        $at = (int) strrpos($account, '@');

        return [substr($account, 0, $at), substr($account, $at + 1)];
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
            throw ErrorCode::NoDatabase->error();
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
