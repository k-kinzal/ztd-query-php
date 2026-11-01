<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An account operand exceeding its name limit or containing an illegal host separator.
 *
 * A null limit denotes an at sign in the host; otherwise the complete
 * decoded operand and its user/host limit are retained for the consumer.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html.
 *
 * @visibility public
 * @example Describing an invalid account host
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure('bad@host', true))->message() // => 'The account host contains an illegal at sign.'
 */
final class AccountNameFailure implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $operand The complete decoded and normalized account operand
     * @param bool $host Whether the operand is the host part
     * @param int|null $limit The exceeded limit, or null for an illegal host separator
     */
    public function __construct(public readonly string $operand, public readonly bool $host, public readonly ?int $limit = null)
    {
    }

    /**
     * Describes the invalid name without imposing a consumer's display limit.
     */
    public function message(): string
    {
        return $this->limit === null ? 'The account host contains an illegal at sign.' : 'The account ' . ($this->host ? 'host' : 'user') . ' exceeds its limit of ' . $this->limit . '.';
    }
}
