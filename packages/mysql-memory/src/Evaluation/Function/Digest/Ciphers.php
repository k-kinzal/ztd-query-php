<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Digest;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The encryption functions: AES_ENCRYPT and AES_DECRYPT, and DES_ENCRYPT and DES_DECRYPT of MySQL 5.6 and 5.7.
 *
 * AES uses the key length and mode of block_encryption_mode (`aes-128-ecb` by default). The key
 * folds the bytes of the key string into the key length with exclusive or, unless a key derivation
 * function is named: `hkdf` (HMAC-SHA-512, with an optional salt and info) or `pbkdf2_hmac`
 * (HMAC-SHA-512, with an optional salt and an iteration count of 1000 to 65535 written in at most
 * five characters, 1000 by default); a name, salt or info that is NULL or longer than 255 bytes is
 * refused. The ECB and CBC modes pad with PKCS #7, and a decryption whose padding is wrong is NULL.
 * The modes other than ECB need an initialization vector of at least 16 bytes, refusing a call
 * without one with ER_WRONG_PARAMCOUNT_TO_NATIVE_FCT and a shorter one with ER_AES_INVALID_IV; ECB
 * ignores a third argument with a warning, never raised as an error. DES encrypts with triple DES in CBC mode under a zero
 * initialization vector: a key string gives the key of EVP_BytesToKey with MD5, the default keys 0
 * to 9 of a server without a key file are zero, and the first byte of the result is 128 plus the
 * key number, 127 for a key string, and a key that does not fit warns, never as an error; the text is padded to whole blocks by `*` and the count of
 * padding bytes. DES_DECRYPT answers a value not marked so unchanged (verified on live 5.7.44 and
 * 8.4 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/encryption-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_block_encryption_mode,
 * https://dev.mysql.com/doc/refman/5.7/en/encryption-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Ciphers
{
    /**
     * The modes of block_encryption_mode in the order the server numbers them, with the name OpenSSL gives each.
     */
    public const MODES = ['ecb' => 'ecb', 'cbc' => 'cbc', 'cfb1' => 'cfb1', 'cfb8' => 'cfb8', 'cfb128' => 'cfb', 'ofb' => 'ofb'];

    /**
     * The key lengths of block_encryption_mode in the order the server numbers them.
     */
    public const SIZES = [128, 192, 256];

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('AES_ENCRYPT', 2, 6, fn (Frame $f, array $a): ?string => $this->aes($f, $a, true)),
            new Routine('AES_DECRYPT', 2, 6, fn (Frame $f, array $a): ?string => $this->aes($f, $a, false)),
            new Routine('DES_ENCRYPT', 1, 2, $this->desEncrypt(...)),
            new Routine('DES_DECRYPT', 1, 2, $this->desDecrypt(...)),
        ];
    }

    /**
     * Reads a value of block_encryption_mode, a name or the number of one, into its lower-case name, or answers null for another value.
     */
    public static function mode(string $value): ?string
    {
        if (preg_match('/\A[0-9]+\z/', $value) === 1) {
            $number = (int) $value;
            $mode = array_keys(self::MODES)[intdiv($number, 3)] ?? null;

            return $mode === null ? null : 'aes-' . self::SIZES[$number % 3] . '-' . $mode;
        }
        $lower = strtolower($value);
        if (preg_match('/\Aaes-(128|192|256)-([a-z0-9]+)\z/', $lower, $parts) !== 1 || !isset(self::MODES[$parts[2]])) {
            return null;
        }

        return $lower;
    }

    /**
     * AES_ENCRYPT and AES_DECRYPT: the text encrypted or decrypted under the key in the mode of block_encryption_mode.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the initialization vector or the key derivation arguments are refused
     */
    public function aes(Frame $frame, array $arguments, bool $encrypt): ?string
    {
        $function = $encrypt ? 'aes_encrypt' : 'aes_decrypt';
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $key = Convert::toText($arguments[1]->evaluate($frame), $arguments[1]->domain());
        if ($text === null || $key === null) {
            return null;
        }
        [, $size, $mode] = explode('-', self::mode((string) $frame->context->variables->read('block_encryption_mode')) ?? 'aes-128-ecb');
        $iv = '';
        if ($mode !== 'ecb') {
            if (!isset($arguments[2])) {
                throw QueryError::WrongParameterCountToNativeFunction->error($function);
            }
            $iv = Convert::toText($arguments[2]->evaluate($frame), $arguments[2]->domain()) ?? '';
            if (strlen($iv) < 16) {
                throw DataError::AesInvalidInitializationVector->error($function, 16);
            }
            $iv = substr($iv, 0, 16);
        } elseif (count($arguments) === 3) {
            $frame->context->diagnostics->warning(QueryError::OptionIgnored, QueryError::OptionIgnored->message('IV'));
        }
        $key = count($arguments) > 3 ? $this->derived($frame, $arguments, $key, max(0, intdiv((int) $size, 8))) : $this->folded($key, intdiv((int) $size, 8));
        $cipher = 'aes-' . $size . '-' . self::MODES[$mode];
        $answer = $encrypt ? openssl_encrypt($text, $cipher, $key, OPENSSL_RAW_DATA, $iv) : openssl_decrypt($text, $cipher, $key, OPENSSL_RAW_DATA, $iv);

        return $answer === false ? null : $answer;
    }

    /**
     * Folds the bytes of a key string into a key of a length with exclusive or.
     */
    public function folded(string $key, int $length): string
    {
        $folded = str_repeat("\0", $length);
        foreach (str_split($key) as $index => $byte) {
            $folded[$index % $length] = $folded[$index % $length] ^ $byte;
        }

        return $folded;
    }

    /**
     * Derives a key of a length with the key derivation function the fourth argument names.
     *
     * @param list<Evaluable> $arguments
     * @param int<0, max> $length
     *
     * @throws \MySqlMemory\Error\SqlError When the name, the salt, the info or the iteration count is refused
     */
    public function derived(Frame $frame, array $arguments, string $key, int $length): string
    {
        $name = $this->option($frame, $arguments[3], 256);
        if ($name !== 'hkdf' && $name !== 'pbkdf2_hmac') {
            throw DataError::AesInvalidKdfName->error();
        }
        $salt = isset($arguments[4]) ? $this->option($frame, $arguments[4], 256) : '';
        if ($name === 'hkdf') {
            $info = isset($arguments[5]) ? $this->option($frame, $arguments[5], 256) : '';

            return substr(hash_hmac('sha512', $info . "\x01", hash_hmac('sha512', $key, $salt, true), true), 0, $length);
        }
        $iterations = isset($arguments[5]) ? $this->option($frame, $arguments[5], 6) : '1000';
        $count = preg_match('/\A[ \t\n\r]*([+-]?[0-9]+)/', $iterations, $digits) === 1 ? (int) $digits[1] : 0;
        if ($count < 1000 || $count > 65535) {
            throw DataError::AesInvalidKdfIterations->error();
        }

        return hash_pbkdf2('sha512', $key, $salt, $count, $length, true);
    }

    /**
     * Reads an argument of the key derivation function as text shorter than a number of bytes.
     *
     * @throws \MySqlMemory\Error\SqlError When the argument is NULL or not shorter
     */
    public function option(Frame $frame, Evaluable $argument, int $limit): string
    {
        $text = Convert::toText($argument->evaluate($frame), $argument->domain());
        if ($text === null || strlen($text) >= $limit) {
            throw DataError::AesInvalidKdfOptionSize->error($limit);
        }

        return $text;
    }

    /**
     * Derives the triple DES key of a key string, as EVP_BytesToKey does with MD5 and no salt.
     */
    public function desKey(string $key): string
    {
        $derived = md5($key, true);

        return $derived . substr(md5($derived . $key, true), 0, 8);
    }

    /**
     * DES_ENCRYPT(text[, key]) of MySQL 5.6 and 5.7: the text under a key number from 0 to 9 or a key string, after the byte that names the key.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When reading the key number raises a warning as an error
     */
    public function desEncrypt(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null) {
            return null;
        }
        if ($text === '') {
            return '';
        }
        $marker = 0;
        $key = str_repeat("\0", 24);
        if (isset($arguments[1])) {
            $value = $arguments[1]->evaluate($frame);
            $domain = $arguments[1]->domain();
            $number = $domain->kind === Kind::Integer ? Convert::toInteger($value, $domain, $frame->context) : null;
            $string = $domain->kind === Kind::Integer ? null : Convert::toText($value, $domain);
            if (($number === null && $string === null) || ($number !== null && ($number < 0 || $number > 9))) {
                $frame->context->diagnostics->warning(QueryError::WrongParametersToProcedure, QueryError::WrongParametersToProcedure->message('des_encrypt'));

                return null;
            }
            [$marker, $key] = $string === null ? [$number, $key] : [127, $this->desKey($string)];
        }
        $tail = 8 - strlen($text) % 8;
        $padded = $text . str_repeat('*', $tail - 1) . chr($tail);

        return chr(128 | $marker) . openssl_encrypt($padded, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8));
    }

    /**
     * DES_DECRYPT(text[, key]) of MySQL 5.6 and 5.7: the text DES_ENCRYPT encrypted, NULL when the key does not fit, the text itself when it is not marked as encrypted.
     *
     * @param list<Evaluable> $arguments
     */
    public function desDecrypt(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null || strlen($text) < 9 || (ord($text[0]) & 128) === 0) {
            return $text;
        }
        $marker = ord($text[0]) & 127;
        if (isset($arguments[1])) {
            $string = Convert::toText($arguments[1]->evaluate($frame), $arguments[1]->domain());
            $key = $string === null ? null : $this->desKey($string);
        } else {
            $key = $marker <= 9 ? str_repeat("\0", 24) : null;
        }
        if ($key === null) {
            $frame->context->diagnostics->warning(QueryError::WrongParametersToProcedure, QueryError::WrongParametersToProcedure->message('des_decrypt'));

            return null;
        }
        $plain = (strlen($text) - 1) % 8 === 0 ? openssl_decrypt(substr($text, 1), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8)) : false;
        if ($plain === false || $plain === '') {
            return null;
        }
        $tail = ord($plain[strlen($plain) - 1]);

        return $tail < 1 || $tail > 8 ? null : substr($plain, 0, -$tail);
    }
}
