<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of the hash, encryption and identifier functions.
 *
 * The digests MD5, SHA1, SHA2 and the readable identifiers BIN_TO_UUID, INET_NTOA and INET6_NTOA
 * are coercible strings of the connection collation, UUID one of utf8mb3_general_ci; the bytes RANDOM_BYTES, UUID_TO_BIN,
 * INET6_ATON and the ciphers answer are binary strings. SHA2 is as long as the digest its length
 * argument names when that is a literal, 128 characters when it is not, and 64 characters for a
 * length it does not take (a binary string of no length in MySQL 5.6). AES_ENCRYPT pads to the
 * next multiple of 16 bytes in the block modes of block_encryption_mode and keeps the length in the
 * stream modes (cfb1, cfb8, cfb128 and ofb); AES_DECRYPT is as long as its argument in characters. DES_ENCRYPT adds 9 bytes and DES_DECRYPT takes them
 * off; PASSWORD of MySQL 5.6 and 5.7 is 41 characters for a literal, none for an empty one and 79
 * otherwise. MySQL 5.6 and 5.7 answer INET_NTOA, INET6_ATON and INET6_NTOA with no decimals
 * (verified on live 5.6.51, 5.7.44 and 8.4 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/encryption-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class DigestResults
{
    /**
     * The modes of block_encryption_mode that encrypt a stream, without padding.
     */
    public const STREAM_MODES = ['cfb1', 'cfb8', 'cfb128', 'ofb'];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $text = static fn (int $length): Closure => static fn (Invocation $call): Domain => self::text($call, $length);
        $legacy = static fn (int $length): Closure => static fn (Invocation $call): Domain => self::legacy($call) ? self::plain($call, $length) : self::text($call, $length);
        $bytes = static fn (int $length): Closure => static fn (Invocation $call): Domain => Domain::string($length, Collation::binary());
        $integer = static fn (int $length, bool $unsigned = false): Closure => static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $length, $unsigned);

        return [
            'MD5' => $text(32),
            'SHA' => $text(40),
            'SHA1' => $text(40),
            'SHA2' => $this->sha2(...),
            'RANDOM_BYTES' => $bytes(1024),
            'UUID' => static fn (Invocation $call): Domain => Domain::string(36, Collation::known('utf8mb3_general_ci'), Field::VarString, Coercibility::Coercible),
            'BIN_TO_UUID' => $text(36),
            'UUID_TO_BIN' => $bytes(16),
            'UUID_SHORT' => $integer(21, true),
            'IS_UUID' => $integer(1),
            'INET_ATON' => $integer(21, true),
            'INET_NTOA' => $legacy(31),
            'INET6_ATON' => static fn (Invocation $call): Domain => self::legacy($call) ? new Domain(Kind::String, Field::VarString, 16, 0, false, Collation::binary()) : Domain::string(16, Collation::binary()),
            'INET6_NTOA' => $legacy(39),
            'IS_IPV4' => $integer(1),
            'IS_IPV6' => $integer(1),
            'IS_IPV4_COMPAT' => $integer(1),
            'IS_IPV4_MAPPED' => $integer(1),
            'AES_ENCRYPT' => static fn (Invocation $call): Domain => Domain::string(self::stream($call) ? self::bytes($call, 0) : 16 * (intdiv(self::bytes($call, 0), 16) + 1), Collation::binary()),
            'AES_DECRYPT' => static fn (Invocation $call): Domain => Domain::string($call->length($call->domain(0)), Collation::binary()),
            'VALIDATE_PASSWORD_STRENGTH' => $integer(10),
            'PASSWORD' => static fn (Invocation $call): Domain => self::text($call, self::password($call)),
            'OLD_PASSWORD' => $text(16),
            'ENCRYPT' => $bytes(13),
            'DES_ENCRYPT' => static fn (Invocation $call): Domain => Domain::string(self::bytes($call, 0) + 9, Collation::binary()),
            'DES_DECRYPT' => static fn (Invocation $call): Domain => Domain::string(self::bytes($call, 0) >= 9 ? self::bytes($call, 0) - 9 : self::bytes($call, 0), Collation::binary()),
        ];
    }

    /**
     * Resolves SHA2: as long as the digest a literal length names, else as long as the longest.
     */
    public function sha2(Invocation $call): Domain
    {
        $bits = self::literal($call->nodes[1] ?? null);
        if ($bits === false) {
            return self::text($call, 128);
        }
        $length = match ($bits) {
            0, 256 => 64,
            224 => 56,
            384 => 96,
            512 => 128,
            default => null,
        };
        if ($length === null && $call->derivation->context->profile->grammar === GrammarRelease::MySql5651) {
            return Domain::string(0, Collation::binary());
        }

        return self::text($call, $length ?? 64);
    }

    /**
     * Answers a coercible string of the connection collation of a length.
     */
    public static function text(Invocation $call, int $length): Domain
    {
        return Domain::string($length, $call->settings->connection, Field::VarString, Coercibility::Coercible);
    }

    /**
     * Reads the integer a literal argument writes, as the server reads it when it resolves the call: null for NULL, false when the argument is not a literal.
     *
     * A decimal or float rounds half away from zero; a string counts by the integer it starts with.
     */
    public static function literal(?object $node): int|false|null
    {
        $negative = false;
        while ($node instanceof Grouped || ($node instanceof Unary && $node->operator === UnaryOperator::Minus)) {
            $negative = $negative !== ($node instanceof Unary);
            $node = $node->operand;
        }
        if ($node instanceof NullLiteral) {
            return null;
        }
        $number = match (true) {
            $node instanceof NumberLiteral => round((float) $node->text),
            $node instanceof StringLiteral => preg_match('/\A[ \t\n\r]*([+-]?[0-9]+)/', $node->value(), $digits) === 1 ? (float) $digits[1] : 0.0,
            default => false,
        };
        if ($number === false) {
            return false;
        }

        return (int) ($negative ? -$number : $number);
    }

    /**
     * Tells whether the call is resolved by MySQL 5.6 or 5.7.
     */
    public static function legacy(Invocation $call): bool
    {
        $release = $call->derivation->context->profile->grammar;

        return $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
    }

    /**
     * Answers a string of the connection collation without decimals, as MySQL 5.6 and 5.7 type the address functions.
     */
    public static function plain(Invocation $call, int $length): Domain
    {
        return new Domain(Kind::String, Field::VarString, $length, 0, false, $call->settings->connection, [], Coercibility::Coercible);
    }

    /**
     * Tells whether block_encryption_mode names a stream mode.
     */
    public static function stream(Invocation $call): bool
    {
        $parts = explode('-', strtolower($call->settings->blockEncryptionMode));

        return in_array($parts[2] ?? 'ecb', self::STREAM_MODES, true);
    }

    /**
     * Answers the number of bytes of the text of an argument.
     */
    public static function bytes(Invocation $call, int $index): int
    {
        $domain = $call->domain($index);

        return $domain->kind === Kind::String ? $domain->byteLength() : $call->length($domain);
    }

    /**
     * Answers the length of PASSWORD: 41 for a literal, 0 for an empty one, 79 otherwise.
     */
    public static function password(Invocation $call): int
    {
        $node = $call->nodes[0] ?? null;
        if ($node instanceof StringLiteral && $node->value() === '') {
            return 0;
        }

        return $node instanceof StringLiteral || $node instanceof NumberLiteral ? 41 : 79;
    }
}
