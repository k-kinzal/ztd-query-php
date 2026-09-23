<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Configuration;

/**
 * MySQL transaction defaults: the session, the global default, or persisted global configuration.
 * @visibility public
 * @example Selecting a transaction policy
 *     \SqlSemantics\Model\Transaction\Configuration\DefaultScope::Session->value // => 'SESSION'
 */
enum DefaultScope: string
{
    case Session = 'SESSION';
    case Global = 'GLOBAL';
    case Persist = 'PERSIST';
    case PersistOnly = 'PERSIST_ONLY';
}
