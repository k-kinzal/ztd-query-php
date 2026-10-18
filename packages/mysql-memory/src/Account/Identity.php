<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

/**
 * The name of an account or a role: a user name and a host.
 *
 * The user name is compared exactly; the host is held in lower case, as the server folds it when
 * it reads an account name, and a name written without a host names the host `%`. The server
 * writes account names as quoted strings in CREATE USER diagnostics, as escaped
 * identifiers in SHOW GRANTS and role-cycle diagnostics, as unescaped backtick
 * labels in authorization and role-membership errors, and plain in column names.
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
     * Answers the identity written to the ASCII Host column of the grant tables.
     * Unsupported characters become one question mark each, while the live cache retains the name.
     */
    public function stored(): self
    {
        $host = preg_replace('/[^\x00-\x7F]/u', '?', $this->host);

        return new self($this->user, $host ?? (string) preg_replace('/[\x80-\xFF]/', '?', $this->host));
    }

    /**
     * Writes the name quoted as strings, as the messages of the account statements write it.
     */
    public function quoted(): string
    {
        return "'" . self::escape($this->user) . "'@'" . self::escape($this->host) . "'";
    }

    /**
     * Writes the name quoted as identifiers, as SHOW GRANTS and role-cycle diagnostics write it.
     *
     * @example A name with a backtick
     *     \MySqlMemory\Account\Identity::of('a`b', '%')->backquoted() // => '`a``b`@`%`'
     */
    public function backquoted(): string
    {
        return '`' . str_replace('`', '``', $this->user) . '`@`' . str_replace('`', '``', $this->host) . '`';
    }

    /**
     * Labels decoded identities in authorization lookup and role-membership diagnostics.
     * Error numbers 3523 and 3530 retain embedded backticks once and display at
     * most 64 bytes of each component, replacing partial UTF-8 bytes with question marks.
     * Observed through MySQL 8.0.44, 8.4.7 and 9.1.0.
     */
    public function authorization(): string
    {
        return '`' . (new \MySqlMemory\Error\DiagnosticText())->bytes($this->user, 64) . '`@`' . (new \MySqlMemory\Error\DiagnosticText())->bytes($this->host, 64) . '`';
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
