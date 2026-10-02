<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\Postgres;

use SqlSemantics\Statement\Operation;

/**
 * A PostgreSQL transaction request with independently specified characteristics.
 * @visibility public
 * @example Requesting a deferrable serializable read-only transaction
 *     (new \SqlSemantics\Statement\Transaction\Postgres\Begin(\SqlSemantics\Statement\Transaction\Postgres\Isolation::Serializable, true, true))->toString() // => 'BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE'
 */
final class Begin implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Omitted characteristics depend on the connection; analysis does not execute preceding SET requests.
     */
    public function __construct(public readonly ?Isolation $isolation = null, public readonly ?bool $readOnly = null, public readonly ?bool $deferrable = null)
    {
    }

    /**
     * Writes each explicitly requested characteristic, including false overrides.
     */
    public function toString(): string
    {
        $options = $this->isolation === null ? [] : ['ISOLATION LEVEL ' . $this->isolation->value];
        if ($this->readOnly !== null) {
            $options[] = $this->readOnly ? 'READ ONLY' : 'READ WRITE';
        }
        if ($this->deferrable !== null) {
            $options[] = $this->deferrable ? 'DEFERRABLE' : 'NOT DEFERRABLE';
        }
        return 'BEGIN' . ($options === [] ? '' : ' ' . implode(', ', $options));
    }
}
