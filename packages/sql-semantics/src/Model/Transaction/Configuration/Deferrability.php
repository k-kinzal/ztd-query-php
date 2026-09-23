<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Configuration;

/**
 * Whether a PostgreSQL serializable read-only transaction may defer its snapshot acquisition.
 * @visibility public
 * @example Selecting a transaction policy
 *     \SqlSemantics\Model\Transaction\Configuration\Deferrability::Deferrable->value // => 'DEFERRABLE'
 */
enum Deferrability: string
{
    case Deferrable = 'DEFERRABLE';
    case NotDeferrable = 'NOT DEFERRABLE';
}
