<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The authentication plugins of the server, the authentication strings they store, and the passwords it generates.
 *
 * caching_sha2_password and sha256_password are loaded; mysql_native_password is disabled, and
 * any other plugin is not loaded (ER_PLUGIN_IS_NOT_LOADED). A password is stored as a salted
 * SHA-256 crypt string: `$A$005$`, 20 salt bytes and 43 digest characters for
 * caching_sha2_password, `$5$`, the salt, `$` and the digest for sha256_password; an empty
 * password stores an empty string. The salt is random, so the string differs each time a
 * password is set, as on the server, and this emulator writes a random digest of the same form.
 * An authentication string given with AS must be empty or have that form
 * (ER_PASSWORD_FORMAT). A random password has generated_random_password_length (20) characters.
 *
 * MySQL 5.6 and 5.7 create accounts with mysql_native_password, which stores `*` and the
 * upper-case hexadecimal double SHA-1 of the password; they load mysql_native_password and
 * sha256_password, and 5.6 also mysql_old_password. A mysql_native_password string given there
 * must be empty or have 41 characters starting with `*`, and the error tells to check the
 * PASSWORD() function (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/caching-sha2-pluggable-authentication.html,
 * https://dev.mysql.com/doc/refman/8.4/en/password-management.html#random-password-generation,
 * https://dev.mysql.com/doc/refman/5.7/en/native-pluggable-authentication.html.
 *
 * @visibility MySqlMemory
 */
final class Credentials
{
    /**
     * The characters of a digest.
     */
    public const DIGITS = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * @param GrammarRelease $release The release whose plugins are loaded
     */
    public function __construct(public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
    }

    /**
     * Tells whether the release is MySQL 5.6 or 5.7, whose accounts use mysql_native_password.
     */
    public function legacy(): bool
    {
        return $this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744;
    }

    /**
     * Answers the plugin an account is created with when the statement names none.
     *
     * @example MySQL 5.7
     *     (new \MySqlMemory\Account\Credentials(\SqlSemantics\Contract\GrammarRelease::MySql5744))->default() // => 'mysql_native_password'
     */
    public function default(): string
    {
        return $this->legacy() ? 'mysql_native_password' : 'caching_sha2_password';
    }

    /**
     * Answers the name of a loaded plugin, in lower case.
     *
     * @throws SqlError When the plugin is not loaded
     */
    public function plugin(string $name): string
    {
        $plugin = strtolower($name);
        $loaded = $this->legacy() ? ['mysql_native_password', 'sha256_password'] : ['caching_sha2_password', 'sha256_password'];
        if ($this->release === GrammarRelease::MySql5651) {
            $loaded[] = 'mysql_old_password';
        }
        if (!in_array($plugin, $loaded, true)) {
            throw AdministrationError::PluginIsNotLoaded->error($name);
        }

        return $plugin;
    }

    /**
     * Answers the authentication string a plugin stores for a password.
     */
    public function hash(string $plugin, string $password): string
    {
        if ($password === '') {
            return '';
        }
        if ($plugin === 'mysql_native_password' || $plugin === 'mysql_old_password') {
            return '*' . strtoupper(sha1(sha1($password, true)));
        }
        $salt = '';
        for ($i = 0; $i < 20; $i++) {
            $byte = random_int(1, 126);
            $salt .= chr($byte === 36 ? 37 : $byte);
        }
        $digest = '';
        for ($i = 0; $i < 43; $i++) {
            $digest .= self::DIGITS[random_int(0, 63)];
        }

        return $plugin === 'sha256_password' ? '$5$' . $salt . '$' . $digest : '$A$005$' . $salt . $digest;
    }

    /**
     * Checks an authentication string given with AS.
     *
     * @throws SqlError When the string does not have the form of the plugin
     */
    public function check(string $plugin, string $hash): void
    {
        if ($plugin === 'mysql_native_password' || $plugin === 'mysql_old_password') {
            if ($hash !== '' && (strlen($hash) !== 41 || $hash[0] !== '*')) {
                throw AccountError::PasswordFormat->error("The password hash doesn't have the expected format. Check if the correct password algorithm is being used with the PASSWORD() function.");
            }

            return;
        }
        $valid = $hash === '' || ($plugin === 'sha256_password' ? strlen($hash) === 67 && str_starts_with($hash, '$5$') : strlen($hash) === 70 && str_starts_with($hash, '$A$'));
        if (!$valid) {
            throw AccountError::PasswordFormat->error();
        }
    }

    /**
     * Generates a random password of the session's configured length.
     *
     * @param int $length The checked generated_random_password_length, between 5 and 255
     */
    public function generate(int $length = 20): string
    {
        $characters = '!"#$%&()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]^_abcdefghijklmnopqrstuvwxyz{|}~';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }
}
