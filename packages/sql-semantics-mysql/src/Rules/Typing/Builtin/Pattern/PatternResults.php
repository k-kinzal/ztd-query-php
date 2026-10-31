<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Pattern;

use Closure;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Resolves the results of the regular expression functions: REGEXP_LIKE, REGEXP_INSTR, REGEXP_SUBSTR and REGEXP_REPLACE.
 *
 * The subject and the pattern aggregate their collations as a comparison does; the replacement
 * of REGEXP_REPLACE does not take part. REGEXP_LIKE is a BIGINT of length 1 and REGEXP_INSTR one
 * of length 21. REGEXP_SUBSTR is a string as long as its subject. REGEXP_REPLACE is a string of
 * as many whole characters as 16777216 bytes hold, a LONGTEXT or LONGBLOB unless those bytes are
 * fewer, as in utf8mb3; its length is reported in units of the shortest character of the set,
 * and it can be NULL when an argument can, or when its character set has characters of more than
 * one byte (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class PatternResults
{
    /**
     * The largest string REGEXP_REPLACE answers, in bytes.
     */
    public const WIDEST = 16777216;

    /**
     * The length of the shortest character of the character sets whose characters are never one byte.
     */
    public const SHORTEST = ['ucs2' => 2, 'utf16' => 2, 'utf16le' => 2, 'utf32' => 4];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        return [
            'REGEXP_LIKE' => fn (Invocation $call): ?Domain => $this->matched($call, 'regexp_like') ? Domain::integer(Field::LongLong, 1) : null,
            'REGEXP_INSTR' => fn (Invocation $call): ?Domain => $this->matched($call, 'regexp_instr') ? Domain::integer() : null,
            'REGEXP_SUBSTR' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0), $call->domain(1)], $call->length($call->domain(0)), 'regexp_substr'),
            'REGEXP_REPLACE' => $this->replaced(...),
        ];
    }

    /**
     * Tells whether the collations of the subject and the pattern mix, reporting the conflict when they do not.
     */
    public function matched(Invocation $call, string $operation): bool
    {
        return $call->collations()->aggregate([$call->domain(0), $call->domain(1)], $operation, $call->derivation, true) !== null;
    }

    /**
     * Resolves the result of REGEXP_REPLACE.
     */
    public function replaced(Invocation $call): ?Domain
    {
        $settled = $call->collations()->aggregate([$call->domain(0), $call->domain(1)], 'regexp_replace', $call->derivation, true);
        if ($settled === null) {
            return null;
        }
        [$collation, $coercibility] = $settled;
        $widest = $collation->charset->maxLength;
        $bytes = intdiv(self::WIDEST, $widest) * $widest;

        return Domain::string(intdiv($bytes, self::SHORTEST[$collation->charset->name] ?? 1), $collation, $bytes > 16777215 ? Field::LongBlob : Field::MediumBlob, $coercibility);
    }

    /**
     * Answers the nullability of a call: REGEXP_REPLACE can also be NULL when its result has characters of more than one byte.
     */
    public function nullability(string $name, ?Domain $type, Nullability $nullability): Nullability
    {
        return strtoupper($name) === 'REGEXP_REPLACE' && $type !== null && $type->collation->charset->maxLength > 1 ? Nullability::Nullable : $nullability;
    }
}
