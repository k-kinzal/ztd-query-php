<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;

/**
 * Checks the numbers of GRANT and REVOKE ON LARGE OBJECT as object identifiers.
 *
 * Rule: PG-LARGE-OBJECT-OID-001. The server reads each number with
 * `oidparse()`: an integer constant within the int4 range is taken as it
 * is (a negative one wraps around), any other number keeps its spelling and
 * is read as an unsigned 32-bit integer with `uint32in_subr()`, which
 * rejects a fraction or an exponent ("invalid input syntax for type oid")
 * and a value beyond 4294967295 ("value … is out of range for type oid");
 * of the negative numbers outside int4 only -2147483648 passes, because
 * only it matches after sign extension. The check judges the exact value:
 * the server reads a constant beyond int4 from its spelling, so a radix
 * prefix other than `0x` or digit separators in such a constant, which
 * `strtoul()` does not read, are judged by their value. Whether a large
 * object exists is not a declaration a context holds and is not reported.
 * Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/datatype-oid.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ObjectIdentifiers
{
    /**
     * The largest integer the lexer keeps as an integer constant.
     */
    private const INT4_MAX = 2147483647;

    /**
     * The largest object identifier.
     */
    private const OID_MAX = 4294967295;

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
        $sign = $identifier->negative ? '-' : '';
        $magnitude = $identifier->magnitude;
        if ($magnitude instanceof NumericConstant) {
            return new AccessProblem(AccessProblemRule::InvalidObjectIdentifier, [$sign . $this->spelling($magnitude)]);
        }
        $value = strlen($magnitude->digits) > 10 ? null : (int) $magnitude->digits;
        $accepted = $value !== null && ($value <= self::INT4_MAX || ($identifier->negative ? $value === self::INT4_MAX + 1 : $value <= self::OID_MAX));

        return $accepted ? null : new AccessProblem(AccessProblemRule::ObjectIdentifierRange, [$sign . $magnitude->digits]);
    }

    /**
     * Spells a numeric constant as the server echoes it: the fraction after a point, the exponent after `e`.
     */
    public function spelling(NumericConstant $constant): string
    {
        $exponent = $constant->exponent === null ? '' : 'e' . $constant->exponent;
        if ($constant->fraction === '' && $exponent !== '') {
            return $constant->integer . $exponent;
        }

        return $constant->integer . '.' . $constant->fraction . $exponent;
    }
}
