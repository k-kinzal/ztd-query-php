<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;

/**
 * Checks the numbers of GRANT and REVOKE ON LARGE OBJECT as object identifiers.
 *
 * Rule: PG-LARGE-OBJECT-OID-001. The server reads each number with
 * `oidparse()`: an integer constant, which fits int4, is taken as it is (a
 * negative one wraps around); a numeric constant keeps its written text, and
 * the text with its sign is read as an unsigned 32-bit integer by
 * `uint32in_subr()`, which calls `strtoul()` with base 0. That reads decimal
 * digits, hexadecimal digits after `0x` and octal digits after a leading
 * zero; any other text, such as a fraction, an exponent, digit separators or
 * the `0o` and `0b` prefixes, is "invalid input syntax for type oid", and a
 * value beyond 4294967295 is "value … is out of range for type oid"; of the
 * negative values outside int4 only -2147483648 passes, because only it
 * matches after sign extension. Whether a large object exists is not a
 * declaration a context holds and is not reported.
 * Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/datatype-oid.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ObjectIdentifiers
{
    /**
     * The largest object identifier.
     */
    private const OID_MAX = '4294967295';

    /**
     * The largest magnitude of a negative object identifier.
     */
    private const NEGATIVE_MAX = '2147483648';

    /**
     * Reports every number that is no object identifier.
     *
     * @param list<SignedNumber> $identifiers
     */
    public function check(Derivation $derivation, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $problem = $this->problem($identifier);
            if ($problem !== null) {
                $derivation->report($problem);
            }
        }
    }

    /**
     * Answers the problem of a number read as an object identifier, or null when the server accepts it.
     */
    public function problem(SignedNumber $identifier): ?AccessProblem
    {
        $magnitude = $identifier->magnitude;
        if ($magnitude instanceof IntegerConstant) {
            return null;
        }
        $value = $this->unsigned($magnitude->text);
        if ($value === null) {
            return new AccessProblem(AccessProblemRule::InvalidObjectIdentifier, [$identifier->text()]);
        }

        return (new Numerals())->within($value, $identifier->negative ? self::NEGATIVE_MAX : self::OID_MAX) ? null : new AccessProblem(AccessProblemRule::ObjectIdentifierRange, [$identifier->text()]);
    }

    /**
     * Answers the canonical decimal digits `strtoul()` with base 0 reads from a whole text, or null when it does not read the whole text.
     */
    public function unsigned(string $text): ?string
    {
        $numerals = new Numerals();

        return match (true) {
            preg_match('/\A0[xX][0-9A-Fa-f]+\z/', $text) === 1 => $numerals->decimal($text),
            preg_match('/\A0[0-7]+\z/', $text) === 1 => $numerals->decimal('0o' . substr($text, 1)),
            preg_match('/\A[0-9]+\z/', $text) === 1 && $text[0] !== '0' => $numerals->canonical($text),
            default => null,
        };
    }
}
