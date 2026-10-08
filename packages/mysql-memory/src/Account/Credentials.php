<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use MySqlMemory\Error\AccountError;
use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\SqlError;

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
 * Source: https://dev.mysql.com/doc/refman/8.4/en/caching-sha2-pluggable-authentication.html,
 * https://dev.mysql.com/doc/refman/8.4/en/password-management.html#random-password-generation.
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
     * Answers the name of a loaded plugin, in lower case.
     *
     * @throws SqlError When the plugin is not loaded
     */
    public function plugin(string $name): string
    {
        $plugin = strtolower($name);
        if ($plugin !== 'caching_sha2_password' && $plugin !== 'sha256_password') {
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
        $valid = $hash === '' || ($plugin === 'sha256_password' ? strlen($hash) === 67 && str_starts_with($hash, '$5$') : strlen($hash) === 70 && str_starts_with($hash, '$A$'));
        if (!$valid) {
            throw AccountError::PasswordFormat->error();
        }
    }

    /**
     * Generates a random password.
     */
    public function generate(): string
    {
        $characters = '!"#$%&()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]^_abcdefghijklmnopqrstuvwxyz{|}~';
        $password = '';
        for ($i = 0; $i < 20; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }
}
