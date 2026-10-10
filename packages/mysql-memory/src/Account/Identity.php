<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

/**
 * The name of an account or a role: a user name and a host.
 *
 * The user name is compared exactly; the host is held in lower case, as the server folds it when
 * it reads an account name, and a name written without a host names the host `%`. The server
 * writes an account name three ways: quoted as a string ('u'@'h') in the messages of CREATE USER,
 * DROP USER and the like, quoted as identifiers (`u`@`h`) in SHOW GRANTS and the messages about
 * roles, and plain (u@h) in a column name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html.
 *
 * @visibility MySqlMemory
 * @example Writing an account name
 *     \MySqlMemory\Account\Identity::of("a'b", 'LocalHost')->quoted() // => "'a\\'b'@'localhost'"
 */
final class Identity
{
    /**
     * @param string $user The user name
     * @param string $host The host, in lower case
     */
    public function __construct(public readonly string $user, public readonly string $host)
    {
    }

    /**
     * Answers the name of a user name and a host as written, folding the host to lower case.
     *
     * @example A name without a host
     *     \MySqlMemory\Account\Identity::of('app', null)->text() // => 'app@%'
     */
    public static function of(string $user, ?string $host): self
    {
        return new self($user, mb_strtolower($host ?? '%', 'UTF-8'));
    }

    /**
     * Answers the key the name is held under.
     *
     * @example A key
     *     \MySqlMemory\Account\Identity::of('a', 'h')->key() // => "a\0h"
     */
    public function key(): string
    {
        return $this->user . "\0" . $this->host;
    }

    /**
     * Writes the name quoted as strings, as the messages of the account statements write it.
     */
    public function quoted(): string
    {
        return "'" . self::escape($this->user) . "'@'" . self::escape($this->host) . "'";
    }

    /**
     * Writes the name quoted as identifiers, as SHOW GRANTS and the messages about roles write it.
     *
     * @example A name with a backtick
     *     \MySqlMemory\Account\Identity::of('a`b', '%')->backquoted() // => '`a``b`@`%`'
     */
    public function backquoted(): string
    {
        return '`' . str_replace('`', '``', $this->user) . '`@`' . str_replace('`', '``', $this->host) . '`';
    }

    /**
     * Writes the name plain, as the column names of SHOW GRANTS and SHOW CREATE USER write it.
     */
    public function text(): string
    {
        return $this->user . '@' . $this->host;
    }

    /**
     * Escapes a text as the server escapes a string it quotes in a message or a statement it writes.
     *
     * @example A quote and a backslash
     *     \MySqlMemory\Account\Identity::escape("a'b\\c") // => "a\\'b\\\\c"
     */
    public static function escape(string $text): string
    {
        return strtr($text, ["\0" => '\\0', "\n" => '\\n', "\r" => '\\r', '\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\x1A" => '\\Z']);
    }
}
