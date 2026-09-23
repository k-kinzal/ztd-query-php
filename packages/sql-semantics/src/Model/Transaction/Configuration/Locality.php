<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Configuration;

/**
 * PostgreSQL setting lifetime requested by the outer SET statement.
 * @visibility public
 * @example Selecting a transaction policy
 *     \SqlSemantics\Model\Transaction\Configuration\Locality::Session->value // => 'SESSION'
 */
enum Locality: string
{
    case Session = 'SESSION';
    case Local = 'LOCAL';
}
