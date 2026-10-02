<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\MySql;

use SqlSemantics\Statement\Operation;

/**
 * Requests a MySQL transaction, its snapshot acquisition, and its access mode.
 * @visibility public
 * @example Requesting a consistent read-only transaction
 *     (new \SqlSemantics\Statement\Transaction\MySql\Start(true, \SqlSemantics\Statement\Transaction\MySql\Access::ReadOnly))->toString() // => 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY'
 */
final class Start implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * An omitted access mode depends on the connection; no session state is simulated.
     */
    public function __construct(public readonly bool $consistentSnapshot = false, public readonly Access $access = Access::SessionDefault)
    {
    }

    /**
     * Writes normalized characteristics from the request's semantic facts.
     */
    public function toString(): string
    {
        $options = $this->consistentSnapshot ? ['WITH CONSISTENT SNAPSHOT'] : [];
        if ($this->access !== Access::SessionDefault) {
            $options[] = $this->access->toString();
        }
        return 'START TRANSACTION' . ($options === [] ? '' : ' ' . implode(', ', $options));
    }
}
