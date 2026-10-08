<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Digest;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The hash functions: MD5, SHA1, SHA2, RANDOM_BYTES and VALIDATE_PASSWORD_STRENGTH, and PASSWORD, OLD_PASSWORD and ENCRYPT of MySQL 5.6 and 5.7.
 *
 * A digest is the lower-case hexadecimal hash of the bytes of the argument, written in the
 * character set of the connection. SHA2 takes a length of 224, 256, 384 or 512 bits, or 0 for 256;
 * any other length, or NULL, gives NULL with ER_WRONG_PARAMETERS_TO_NATIVE_FCT, which the server
 * raises once when it resolves a length known then and for each row otherwise, never as an error.
 * RANDOM_BYTES takes 1 to 1024 bytes, and refuses any other count with ER_DATA_OUT_OF_RANGE.
 * VALIDATE_PASSWORD_STRENGTH answers 0 without the validate_password component, which the
 * emulator does not have. PASSWORD is `*` and the upper-case SHA1 of the SHA1 of the password, an
 * empty string for an empty password or NULL, and MySQL 5.7 warns that it is deprecated for each
 * password it hashes, never as an error. OLD_PASSWORD (5.6) is the hash of MySQL 3.23, which skips spaces and tabs.
 * ENCRYPT is crypt(3): NULL for a salt it does not take, and a random two-character salt when none
 * is given (verified on live 5.6.51, 5.7.44 and 8.4 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/encryption-functions.html,
 * https://dev.mysql.com/doc/refman/5.7/en/encryption-functions.html,
 * https://dev.mysql.com/doc/refman/5.6/en/encryption-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Hashes
{
    /**
     * The digest of each length SHA2 takes, by number of bits.
     */
    public const SHA2 = [0 => 'sha256', 224 => 'sha224', 256 => 'sha256', 384 => 'sha384', 512 => 'sha512'];

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('MD5', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->digest($f, $a[0], $r, 'md5')),
            new Routine('SHA', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->digest($f, $a[0], $r, 'sha1')),
            new Routine('SHA1', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->digest($f, $a[0], $r, 'sha1')),
            new Routine('SHA2', 2, 2, $this->sha2(...), 1, $this->known(...)),
            new Routine('RANDOM_BYTES', 1, 1, $this->randomBytes(...)),
            new Routine('VALIDATE_PASSWORD_STRENGTH', 1, 1, fn (Frame $f, array $a): ?int => $a[0]->evaluate($f) === null ? null : 0),
            new Routine('PASSWORD', 1, 1, $this->password(...)),
            new Routine('OLD_PASSWORD', 1, 1, $this->oldPassword(...)),
            new Routine('ENCRYPT', 1, 2, $this->encrypt(...)),
        ];
    }

    /**
     * Writes an ASCII text in the character set of a string result.
     */
    public function written(string $text, Domain $result): string
    {
        return $result->kind === Kind::String ? Encoding::convert($text, Charset::known('utf8mb4'), $result->collation->charset) : $text;
    }

    /**
     * Hashes the bytes of an argument into lower-case hexadecimal digits, or answers null for NULL.
     */
    public function digest(Frame $frame, Evaluable $argument, Domain $result, string $algorithm): ?string
    {
        $text = Convert::toText($argument->evaluate($frame), $argument->domain());

        return $text === null ? null : $this->written(hash($algorithm, $text), $result);
    }

    /**
     * Checks a length of SHA2 known when the statement is resolved, and keeps its value for the rows.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known
     * @return list<Evaluable>
     *
     * @throws \MySqlMemory\Error\SqlError When reading the length raises a warning as an error
     */
    public function known(Frame $frame, array $arguments, array $known): array
    {
        if (!$known[1]) {
            return $arguments;
        }
        $value = $arguments[1]->evaluate($frame);
        $bits = Convert::toInteger($value, $arguments[1]->domain(), $frame->context);
        if ($bits === null || !isset(self::SHA2[$bits])) {
            $frame->context->diagnostics->warning(QueryError::WrongParametersToNativeFunction, QueryError::WrongParametersToNativeFunction->message('sha2'));
        }

        return [$arguments[0], new Constant($arguments[1]->domain(), $value)];
    }

    /**
     * SHA2(text, bits): the SHA-2 digest of a length; NULL for a length it does not take.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When reading the length raises a warning as an error
     */
    public function sha2(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null) {
            return null;
        }
        $bits = Convert::toInteger($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context);
        if ($bits === null || !isset(self::SHA2[$bits])) {
            if (!$arguments[1] instanceof Constant) {
                $frame->context->diagnostics->warning(QueryError::WrongParametersToNativeFunction, QueryError::WrongParametersToNativeFunction->message('sha2'));
            }

            return null;
        }

        return $this->written(hash(self::SHA2[$bits], $text), $result);
    }

    /**
     * RANDOM_BYTES(count): that many random bytes, from 1 to 1024.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the count is out of range
     */
    public function randomBytes(Frame $frame, array $arguments, Domain $result): ?string
    {
        $count = Convert::toInteger($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        if ($count === null) {
            return null;
        }
        if ($count < 1 || $count > 1024) {
            throw DataError::DataOutOfRange->error('length', 'random_bytes');
        }

        return random_bytes($count);
    }

    /**
     * PASSWORD(text) of MySQL 5.6 and 5.7: `*` and the upper-case double SHA1, or an empty string for an empty text or NULL.
     *
     * @param list<Evaluable> $arguments
     */
    public function password(Frame $frame, array $arguments, Domain $result): string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null || $text === '') {
            return '';
        }
        if ($frame->context->modes->release === GrammarRelease::MySql5744) {
            $frame->context->diagnostics->warning(StatementError::DeprecatedSyntaxNoReplacement, StatementError::DeprecatedSyntaxNoReplacement->message('PASSWORD'));
        }

        return $this->written('*' . strtoupper(sha1(sha1($text, true))), $result);
    }

    /**
     * OLD_PASSWORD(text) of MySQL 5.6: the 16 hexadecimal digits of the hash of MySQL 3.23, which skips spaces and tabs.
     *
     * @param list<Evaluable> $arguments
     */
    public function oldPassword(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null || $text === '') {
            return $text;
        }
        $first = 1345345333;
        $second = 0x12345671;
        $add = 7;
        foreach (str_split($text) as $character) {
            if ($character === ' ' || $character === "\t") {
                continue;
            }
            $byte = ord($character);
            $first = ($first ^ (((($first & 63) + $add) * $byte + ($first << 8)) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $second = ($second + ((($second << 8) & 0xFFFFFFFF) ^ $first)) & 0xFFFFFFFF;
            $add += $byte;
        }

        return $this->written(sprintf('%08x%08x', $first & 0x7FFFFFFF, $second & 0x7FFFFFFF), $result);
    }

    /**
     * ENCRYPT(text[, salt]) of MySQL 5.6 and 5.7: crypt(3) of the text, NULL for a salt it does not take.
     *
     * @param list<Evaluable> $arguments
     */
    public function encrypt(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if (isset($arguments[1])) {
            $salt = Convert::toText($arguments[1]->evaluate($frame), $arguments[1]->domain());
        } else {
            $alphabet = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            $salt = $alphabet[random_int(0, 63)] . $alphabet[random_int(0, 63)];
        }
        if ($text === null || $salt === null || strlen($salt) < 2) {
            return null;
        }
        $hashed = crypt($text, $salt);

        return str_starts_with($hashed, '*') ? null : $hashed;
    }
}
