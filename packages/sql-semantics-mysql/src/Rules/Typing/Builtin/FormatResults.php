<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of the string functions that format, encode, pick or convert: FORMAT, ELT, MAKE_SET, EXPORT_SET, SOUNDEX, QUOTE, ORD, the base conversions and the compression functions.
 *
 * A string result is a VARCHAR up to 65535 bytes, a MEDIUMBLOB up to 16777215 and a LONGBLOB
 * beyond, whose length then counts bytes. The lengths the server reports, verified on a live 8.4
 * server, are:
 * - FORMAT: the argument, a separator for each three of its characters, and 32 more for the
 *   point and the 30 decimals it can take, in the connection collation;
 * - ELT: its longest string, MAKE_SET: all its strings and a comma between each two, in the
 *   collation they aggregate to;
 * - EXPORT_SET: 64 times its longer string and 63 separators, a string converted to binary
 *   counting its bytes;
 * - SOUNDEX: its argument, at least 4, in the collation of the argument (binary for NULL);
 * - QUOTE: twice its argument and the quotes, at least 4; a binary string or NULL takes the
 *   connection collation, a number or a temporal value latin1_swedish_ci;
 * - TO_BASE64 and FROM_BASE64: the encoding of the bytes of the argument with a newline after
 *   each 76 characters, and three bytes for each four;
 * - BIN, OCT and CONV: 65 characters; HEX twice the bytes of a string and 16 for a number;
 *   UNHEX half the bytes of its argument;
 * - COMPRESS: what zlib bounds the compression of the bytes to, with the 4-byte length and a
 *   byte for a trailing period; UNCOMPRESS and LOAD_FILE 16777216 bytes;
 * - ORD a BIGINT of 21 digits, UNCOMPRESSED_LENGTH one of 10.
 * MySQL 5.6 and 5.7 (verified on live 5.6.51 and 5.7.44 servers) add 33 to FORMAT, write BIN,
 * OCT and CONV in 64 characters, HEX of a number in twice its length, and COMPRESS in a fifth
 * more than its bytes and 12; their HEX and UNHEX have no decimals.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/encryption-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html#function_conv.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class FormatResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $radix = fn (Invocation $call): Domain => $this->sized($this->legacy($call) ? 64 : 65, $call->settings->connection, Coercibility::Coercible);
        $blob = static fn (Invocation $call): Domain => Domain::string(16777216, Collation::binary(), Field::LongBlob, Coercibility::Coercible);

        return [
            'FORMAT' => fn (Invocation $call): Domain => $this->sized($call->length($call->domain(0)) + intdiv($call->length($call->domain(0)), 3) + ($this->legacy($call) ? 33 : 32), $call->settings->connection, Coercibility::Coercible),
            'ELT' => fn (Invocation $call): ?Domain => $this->settled($call, array_slice($call->domains, 1), 'elt', static fn (Collation $collation): int => max(0, ...array_map($call->length(...), array_slice($call->domains, 1)))),
            'MAKE_SET' => fn (Invocation $call): ?Domain => $this->settled($call, array_slice($call->domains, 1), 'make_set', static fn (Collation $collation): int => array_sum(array_map($call->length(...), array_slice($call->domains, 1))) + count($call->domains) - 2),
            'EXPORT_SET' => fn (Invocation $call): ?Domain => $this->settled($call, array_slice($call->domains, 1, 3), 'export_set', fn (Collation $collation): int => 64 * max($this->width($call, $call->domain(1), $collation), $this->width($call, $call->domain(2), $collation)) + 63 * (count($call->domains) > 3 ? $this->width($call, $call->domain(3), $collation) : 1)),
            'SOUNDEX' => $this->soundex(...),
            'QUOTE' => $this->quote(...),
            'ORD' => static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, 21),
            'TO_BASE64' => fn (Invocation $call): Domain => $this->sized($this->encoded($this->bytes($call->domain(0))), $call->settings->connection, Coercibility::Coercible),
            'FROM_BASE64' => fn (Invocation $call): Domain => $this->sized(intdiv($this->bytes($call->domain(0)) * 3, 4), Collation::binary(), Coercibility::Coercible),
            'BIN' => $radix,
            'OCT' => $radix,
            'CONV' => $radix,
            'HEX' => fn (Invocation $call): Domain => $this->undecimal($call, $this->sized($this->legacy($call) && $call->domain(0)->kind->numeric() ? 2 * $call->length($call->domain(0)) : $this->digitsOf($call->domain(0)), $call->settings->connection, Coercibility::Coercible)),
            'UNHEX' => fn (Invocation $call): Domain => $this->undecimal($call, $this->sized(intdiv($this->bytes($call->domain(0)) + 1, 2), Collation::binary(), Coercibility::Coercible)),
            'COMPRESS' => fn (Invocation $call): Domain => $this->sized($this->legacy($call) ? intdiv($this->bytes($call->domain(0)) * 120, 100) + 12 : $this->bound($this->bytes($call->domain(0))), Collation::binary(), Coercibility::Coercible),
            'UNCOMPRESS' => $blob,
            'LOAD_FILE' => $blob,
            'UNCOMPRESSED_LENGTH' => static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, 10),
        ];
    }

    /**
     * Tells whether MySQL 5.6 or 5.7 resolves the call.
     */
    public function legacy(Invocation $call): bool
    {
        $grammar = $call->derivation->context->profile->grammar;

        return $grammar === GrammarRelease::MySql5651 || $grammar === GrammarRelease::MySql5744;
    }

    /**
     * Answers the result of HEX or UNHEX, which MySQL 5.6 and 5.7 report with no decimals.
     */
    public function undecimal(Invocation $call, Domain $domain): Domain
    {
        return $this->legacy($call) ? new Domain(Kind::String, $domain->field, $domain->length, 0, false, $domain->collation, [], $domain->coercibility) : $domain;
    }

    /**
     * Answers a string of a number of characters: a VARCHAR up to 65535 bytes, else a MEDIUMBLOB or LONGBLOB as long as its bytes.
     */
    public function sized(int $characters, Collation $collation, Coercibility $coercibility): Domain
    {
        $bytes = $characters * $collation->charset->maxLength;
        if ($bytes <= 65535) {
            return Domain::string($characters, $collation, Field::VarString, $coercibility);
        }

        return Domain::string(min($bytes, 4294967295), $collation, $bytes > 16777215 ? Field::LongBlob : Field::MediumBlob, $coercibility);
    }

    /**
     * Resolves a string in the collation some arguments aggregate to, as long as a length in that collation, or null after a conflict.
     *
     * A string made of numbers is coercible.
     *
     * @param list<Domain> $domains The arguments whose collations take part
     * @param Closure(Collation): int $length The length of the result in a collation
     */
    public function settled(Invocation $call, array $domains, string $operation, Closure $length): ?Domain
    {
        $settled = $call->collations()->aggregate($domains, $operation, $call->derivation);
        if ($settled === null) {
            return null;
        }

        return $this->sized($length($settled[0]), $settled[0], $settled[1] === Coercibility::Numeric ? Coercibility::Coercible : $settled[1]);
    }

    /**
     * Answers the length of an argument in the collation of a result: its bytes when a string of another character set becomes binary.
     */
    public function width(Invocation $call, Domain $domain, Collation $collation): int
    {
        return $collation->charset->name === 'binary' && $domain->kind === Kind::String && $domain->collation->charset->name !== 'binary' ? $domain->byteLength() : $call->length($domain);
    }

    /**
     * Answers the length of HEX: two digits for each byte of a string, each character of a temporal or JSON value, and 16 for a number.
     */
    public function digitsOf(Domain $domain): int
    {
        return match ($domain->kind) {
            Kind::String => $domain->byteLength() * 2,
            Kind::Date, Kind::Time, Kind::DateTime, Kind::Json => $domain->length * 2,
            Kind::Null => 0,
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::Year, Kind::Bit => 16,
        };
    }

    /**
     * Answers the bytes the text of a value takes: the bytes of a string, the display length of anything else.
     */
    public function bytes(Domain $domain): int
    {
        return $domain->kind === Kind::Null ? 0 : $domain->byteLength();
    }

    /**
     * Answers the length of the base64 encoding of a number of bytes, with a newline after each 76 characters.
     */
    public function encoded(int $bytes): int
    {
        $characters = 4 * intdiv($bytes + 2, 3);

        return $characters === 0 ? 0 : $characters + intdiv($characters - 1, 76);
    }

    /**
     * Answers the most bytes COMPRESS answers for a number of bytes: the zlib bound, the 4-byte length and a period.
     */
    public function bound(int $bytes): int
    {
        return $bytes + ($bytes >> 12) + ($bytes >> 14) + ($bytes >> 25) + 13 + 5;
    }

    /**
     * Resolves INSERT: a string in the collation of its first argument, whatever the collation of the string inserted, as long as both in that collation.
     *
     * A first argument that is no string takes the connection collation (verified on a live 8.4 server).
     */
    public function inserted(Invocation $call): Domain
    {
        $domain = $call->domain(0);
        [$collation, $coercibility] = $domain->kind === Kind::String ? [$domain->collation, $domain->coercibility] : [$call->settings->connection, Coercibility::Coercible];

        return $this->sized($this->width($call, $domain, $collation) + $this->width($call, $call->domain(3), $collation), $collation, $coercibility);
    }

    /**
     * Resolves SOUNDEX: a string as long as its argument, at least 4, in the collation of the argument.
     *
     * NULL answers a binary string; a value that is no string the connection collation.
     */
    public function soundex(Invocation $call): Domain
    {
        $domain = $call->domain(0);
        $length = max(4, $call->length($domain));

        return match (true) {
            $domain->kind === Kind::Null => $this->sized($length, Collation::binary(), Coercibility::Ignorable),
            $domain->kind === Kind::String => $this->sized($length, $domain->collation, $domain->coercibility),
            default => $this->sized($length, $call->settings->connection, Coercibility::Coercible),
        };
    }

    /**
     * Resolves QUOTE: twice the argument and two quotes, at least the 4 characters of NULL.
     *
     * A string keeps its collation, a binary string takes the connection collation, NULL the
     * connection collation as ignorable, and a number or temporal value latin1_swedish_ci as numeric.
     */
    public function quote(Invocation $call): Domain
    {
        $domain = $call->domain(0);
        $length = max(4, 2 * $call->length($domain) + 2);

        return match (true) {
            $domain->kind === Kind::Null => $this->sized($length, $call->settings->connection, Coercibility::Ignorable),
            $domain->kind === Kind::String && $domain->collation->charset->name === 'binary' => $this->sized($length, $call->settings->connection, Coercibility::Coercible),
            $domain->kind === Kind::String => $this->sized($length, $domain->collation, $domain->coercibility),
            default => $this->sized($length, Collation::known('latin1_swedish_ci'), Coercibility::Numeric),
        };
    }
}
