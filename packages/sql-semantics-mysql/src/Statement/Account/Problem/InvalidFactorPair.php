<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Two factor changes of one ALTER USER account that name the same factor, or that add the third factor before the second.
 *
 * The server rejects the statement while parsing it with
 * ER_MFA_METHODS_IDENTICAL or ER_MFA_METHODS_INVALID_ORDER.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-multifactor.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair(true))->message() // => 'Both factor changes name the same factor (ER_MFA_METHODS_IDENTICAL).'
 */
final class InvalidFactorPair implements Diagnostic
{
    use Snapshot;

    /**
     * @param bool $identical Whether both changes name the same factor; otherwise the order is wrong
     */
    public function __construct(public readonly bool $identical)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->identical
            ? 'Both factor changes name the same factor (ER_MFA_METHODS_IDENTICAL).'
            : 'The third factor is added before the second (ER_MFA_METHODS_INVALID_ORDER).';
    }
}
