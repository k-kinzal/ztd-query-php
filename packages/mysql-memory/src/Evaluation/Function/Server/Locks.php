<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Server;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Registry\Threads;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The locking functions GET_LOCK, RELEASE_LOCK, RELEASE_ALL_LOCKS, IS_FREE_LOCK and IS_USED_LOCK: named user-level locks shared by the sessions of one server.
 *
 * A lock name is read in utf8mb3 and compared without regard to letter case, but with regard to
 * accents and trailing spaces. A name that is NULL, empty, or holds a character utf8mb3 cannot
 * hold is ER_USER_LOCK_WRONG_NAME, quoting the name as utf8mb3 writes it or a binary name with its
 * bytes outside printable ASCII as `\xHH`; a name of more than 64 characters is
 * ER_USER_LOCK_OVERLONG_NAME (ER_USER_LOCK_WRONG_NAME, in a shorter message, in MySQL 5.7). MySQL
 * 5.6 answers NULL for a NULL or empty name and limits no length. The timeout of GET_LOCK() is
 * read as an integer number of seconds; a negative timeout waits without end. A lock another
 * session holds makes GET_LOCK() wait for the timeout and answer 0: the emulator runs one statement
 * at a time, so the holder cannot release it meanwhile, and the wait passes on the clock of the
 * server without stalling the caller; a wait without end answers 0 at once (verified on live
 * 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html,
 * https://dev.mysql.com/doc/refman/5.6/en/locking-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Locks
{
    /**
     * The longest lock name, in characters.
     */
    public const LONGEST = 64;

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('GET_LOCK', 2, 2, $this->get(...)),
            new Routine('RELEASE_LOCK', 1, 1, fn (Frame $f, array $a): ?int => ($key = $this->key($f, $a[0])) === null ? null : $this->threads($f)->release($key, $f->context->variables->connection)),
            new Routine('RELEASE_ALL_LOCKS', 0, 0, fn (Frame $f): int => $this->threads($f)->releaseAll($f->context->variables->connection)),
            new Routine('IS_FREE_LOCK', 1, 1, fn (Frame $f, array $a): ?int => ($key = $this->key($f, $a[0])) === null ? null : ($this->threads($f)->owner($key) === null ? 1 : 0)),
            new Routine('IS_USED_LOCK', 1, 1, fn (Frame $f, array $a): ?int => ($key = $this->key($f, $a[0])) === null ? null : $this->threads($f)->owner($key)),
        ];
    }

    /**
     * GET_LOCK(name, timeout): 1 when the session holds the lock, 0 when another session kept it for the whole timeout.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the name is not a valid lock name
     */
    public function get(Frame $frame, array $arguments): ?int
    {
        $key = $this->key($frame, $arguments[0]);
        $timeout = Convert::toInteger($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context) ?? 0;
        if ($key === null) {
            return null;
        }
        $threads = $this->threads($frame);
        if ($threads->acquire($key, $frame->context->variables->connection, $frame->context->modes->release === GrammarRelease::MySql5651)) {
            return 1;
        }
        if ($timeout > 0) {
            $threads->pass($timeout);
        }

        return 0;
    }

    /**
     * Answers the threads of the server the statement runs on.
     */
    public function threads(Frame $frame): Threads
    {
        return $frame->context->variables->instance->registry->threads;
    }

    /**
     * Reads a lock name and answers the key it is held under, or null for a NULL or empty name in MySQL 5.6.
     *
     * @throws SqlError When the name is not a valid lock name
     */
    public function key(Frame $frame, Evaluable $argument): ?string
    {
        $release = $frame->context->modes->release;
        $domain = $argument->domain();
        $text = Convert::toText($argument->evaluate($frame), $domain);
        if ($release === GrammarRelease::MySql5651 && ($text === null || $text === '')) {
            return null;
        }
        if ($text === null || $text === '') {
            throw $this->wrong($text ?? 'NULL', $release);
        }
        $charset = $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4');
        $utf8 = Charset::known('utf8mb3');
        if ($charset === Charset::binary()) {
            if (!Encoding::valid($text, $utf8)) {
                throw $this->wrong(Convert::shown($text, $charset), $release);
            }
            $name = $text;
        } else {
            $name = Encoding::convert($text, $charset, $utf8);
            if (Encoding::convertible($text, $charset, $utf8) < strlen($text)) {
                throw $this->wrong($name, $release);
            }
        }
        if ($release !== GrammarRelease::MySql5651 && mb_strlen($name, 'UTF-8') > self::LONGEST) {
            throw $release === GrammarRelease::MySql5744 ? $this->wrong($name, $release) : AdministrationError::UserLockOverlongName->error($name, self::LONGEST);
        }

        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * Answers the error of a lock name that is not valid, in the words of the release.
     */
    public function wrong(string $name, GrammarRelease $release): SqlError
    {
        return $release === GrammarRelease::MySql5744
            ? new SqlError(AdministrationError::UserLockWrongName, "Incorrect user-level lock name '" . $name . "'.")
            : AdministrationError::UserLockWrongName->error($name);
    }
}
