<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\MySql;

use SqlSemantics\Statement\Operation;

/**
 * Requests MySQL rollback with independent transaction chaining and connection release choices.
 * @visibility public
 * @example Overriding connection defaults explicitly
 *     (new \SqlSemantics\Statement\Transaction\MySql\Rollback(false, false))->toString() // => 'ROLLBACK AND NO CHAIN NO RELEASE'
 */
final class Rollback implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Null means the connection's completion policy; false is an explicit NO request.
     */
    public function __construct(public readonly ?bool $chain = null, public readonly ?bool $release = null)
    {
    }

    /**
     * A transaction cannot both chain into a new transaction and release its connection.
     */
    public function conflicts(): bool
    {
        return $this->chain === true && $this->release === true;
    }

    /**
     * Reconstructs explicit choices without inferring omitted connection configuration.
     */
    public function toString(): string
    {
        return 'ROLLBACK' . ($this->chain === null ? '' : ($this->chain ? ' AND CHAIN' : ' AND NO CHAIN')) . ($this->release === null ? '' : ($this->release ? ' RELEASE' : ' NO RELEASE'));
    }
}
